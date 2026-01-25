<?php
/**
 * Zookeeper Online
 *
 * @author Jim Mason <jmason@ibinx.com>
 * @copyright Copyright (C) 1997-2026 Jim Mason <jmason@ibinx.com>
 * @link https://zookeeper.ibinx.com/
 * @license GPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License,
 * version 3, along with this program.  If not, see
 * http://www.gnu.org/licenses/
 *
 */

namespace ZK\Controllers;

use ZK\Engine\Engine;

use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;

class Turnstile implements IController {
    const TTL_SECONDS = 8 * 60 * 60;  // 8 hour token validation
    const INCLUDE_CLIENT_ADDR = true;

    const DEFAULT_RESOLVER = "8.8.8.8";  // google DNS
    const RESOLVER_TIMEOUT = 0.3;  // in sec

    // TBD refactor me out
    /**
     * `gethostbyaddr` and `gethostbynamel` replacement with timeout
     *
     * If given an IP address, returns the FQDN string; if given a
     * hostname, returns an array<string> of addresses.  Upon failure,
     * (e.g., no record found, timeout), returns false.
     *
     * adapted from original code and concept presented at
     * https://www.php.net/manual/en/function.gethostbyaddr.php#46869
     *
     * @param string $name IP address or FQDN to resolve
     * @param string $dns IP address of DNS resolver
     * @param float $timeout timeout in seconds (optional; default 1)
     * @return string|array|false host name or array of IP address on success or false on failure
     */
    public static function dnslookup(string $name, string $dns, float $timeout = 1.0): string|array|false {
        // build query name
        if (filter_var($name, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $octets = array_reverse(explode('.', $name));
            $queryName = implode('.', $octets) . '.in-addr.arpa.';
            $qtype = 12; // PTR
        } elseif (filter_var($name, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $hex = unpack('H*', inet_pton($name))[1];
            $nibbles = array_reverse(str_split($hex));
            $queryName = implode('.', $nibbles) . '.ip6.arpa.';
            $qtype = 12; // PTR
        } elseif (filter_var($name, FILTER_VALIDATE_DOMAIN)) {
            $queryName = rtrim($name, '.') . '.';
            $qtype = 1; // A
        } else
            return false;

        // encode query name to DNS wire format
        $encodeName = function (string $name): string {
            $parts = explode('.', rtrim($name, '.'));
            $out = '';
            foreach ($parts as $p) {
                $l = strlen($p);
                if ($l > 63) return ''; // invalid
                $out .= chr($l) . $p;
            }
            return $out . "\0";
        };

        $qname = $encodeName($queryName);
        if ($qname === '')
            return false;

        // construct DNS query packet
        $id = random_int(0, 0xFFFF);
        $flags = 0x0100; // recursion desired
        $qdcount = 1;
        $ancount = 0;
        $nscount = 0;
        $arcount = 0;

        $packet = pack(
            'nnnnnn',
            $id, $flags, $qdcount, $ancount, $nscount, $arcount
        );
        $packet .= $qname;
        $packet .= pack('nn', $qtype, 1); // QTYPE, QCLASS IN

        // send UDP query and read response
        $sock = @fsockopen("udp://$dns", 53, $errno, $errstr, $timeout);
        if (!$sock) return false;
        stream_set_timeout($sock, 0, (int)($timeout * 1e6));
        fwrite($sock, $packet);
        $response = @fread($sock, 4096);
        fclose($sock);

        if ($response === false || strlen($response) < 12)
            return false;

        $buf = $response;
        $len = strlen($buf);

        // parse DNS header
        $header = unpack('nid/nflags/nqd/nan/nns/nar', substr($buf, 0, 12));
        $id2 = $header['id'];
        $flags = $header['flags'];
        $qd = $header['qd'];
        $an = $header['an'];
        $ns = $header['ns'];
        $ar = $header['ar'];
        $off = 12;

        // response ID mismatch or no answers, bail
        if ($id2 !== $id || $an < 1) return false;

        // decode DNS domain name with compression
        $expandName = function($buf, $offset) use (&$expandName, $len) {
            $labels = [];
            $jumped = false;
            $orig = $offset;

            while (true) {
                if ($offset >= $len) return [false, 0];
                $c = ord($buf[$offset]);

                // pointer
                if (($c & 0xC0) === 0xC0) {
                    if ($offset + 1 >= $len) return [false, 0];
                    $ptr = (($c & 0x3F) << 8) | ord($buf[$offset + 1]);
                    $offset += 2;
                    if ($ptr >= $len) return [false, 0];
                    list($name, $_) = $expandName($buf, $ptr);
                    if ($name === false) return [false, 0];
                    $labels[] = $name;
                    $jumped = true;
                    break;
                }

                // end of name
                if ($c === 0) {
                    $offset++;
                    break;
                }

                // literal label
                $l = $c;
                $offset++;
                if ($offset + $l > $len) return [false, 0];
                $labels[] = substr($buf, $offset, $l);
                $offset += $l;
            }

            $name = implode('.', $labels);
            return [$name, $jumped ? ($offset - $orig > 0 ? $offset - $orig : 2) : ($offset - $orig)];
        };

        // skip question section
        for ($i = 0; $i < $qd; $i++) {
            list($_name, $consumed) = $expandName($buf, $off);
            if ($_name === false) return false;
            $off += $consumed;
            if ($off + 4 > $len) return false;
            $off += 4; // QTYPE + QCLASS
        }

        // parse answer RRs
        $answers = [];
        for ($i = 0; $i < $an; $i++) {
            // NAME
            list($_name, $consumed) = $expandName($buf, $off);
            if ($_name === false) return false;
            $off += $consumed;

            // TYPE, CLASS, TTL, RDLENGTH
            if ($off + 10 > $len) return false;
            $rr = unpack('ntype/nclass/Nttl/nrdlen', substr($buf, $off, 10));
            $off += 10;

            $rdlen = $rr['rdlen'];
            if ($off + $rdlen > $len) return false;

            if ($rr['type'] === 12 && $qtype == 12) { // PTR
                list($ptrName, $_) = $expandName($buf, $off);
                return $ptrName ?: false;
            } elseif ($rr['type'] === 1 && $qtype === 1 && $rdlen === 4) { // A
                $addr = inet_ntop(substr($buf, $off, 4));
                if ($addr !== false)
                    $answers[] = $addr;
            } elseif ($rr['type'] === 28 && $qtype === 1 && $rdlen === 16) { // AAAA record may appear even if we asked for A
                $addr = inet_ntop(substr($buf, $off, 16));
                if ($addr !== false)
                    $answers[] = $addr;
            }

            // advance to next record
            $off += $rdlen;
        }

        if (!empty($answers))
            return array_values(array_unique($answers));

        return false;
    }

    public static function validate() {
        // Nothing to do if turnstile is disabled or authenticated user
        $config = Engine::param('turnstile');
        if (!$config || !isset($config['secret']) ||
                Engine::session()->isAuth('u'))
            return true;

        // Nothing to do if edge prevalidation has already run
        if (($config['prevalidate'] ?? false) &&
                $_SERVER[strtoupper($config['prevalidate'])] ?? false)
            return true;

        $cookie = $_COOKIE['turnstile'] ?? '';
        if (!$cookie) {
            // allow whitelisted traffic through
            $addr = explode(',', $_SERVER['REMOTE_ADDR'])[0];
            $domain = PushServer::lruCache($addr);
            if (!$domain) {
                $domain = self::dnslookup($addr,
                            $config['resolver'] ?? self::DEFAULT_RESOLVER,
                            self::RESOLVER_TIMEOUT);
                if ($domain)
                    PushServer::lruCache($addr, $domain);
            }

            $allowed = $domain ? array_filter($config['whitelist'] ?? [],
                            fn($suffix) => str_ends_with($domain, $suffix)) : false;
            $whitelisted = !empty($allowed);

            // forward-confirm the reverse DNS (FCrDNS)
            if ($whitelisted) {
                $addrs = PushServer::lruCache($domain);
                if ($addrs)
                    $addrs = explode(',', $addrs);
                else {
                    $addrs = self::dnslookup($domain,
                                $config['resolver'] ?? self::DEFAULT_RESOLVER,
                                self::RESOLVER_TIMEOUT);
                    if ($addrs)
                        PushServer::lruCache($domain, implode(',', $addrs));
                }

                // discard if forward lookup does not return the address
                if (!$addrs || !in_array($addr, $addrs)) {
                    error_log("DNS mismatch: $addr $domain");
                    $whitelisted = false;
                }
            }

            return $whitelisted;
        }

        $token = json_decode(base64_decode($cookie));

        // check that the token has not expired
        $expires = $token->expires ?? 0;
        if (time() > $expires) {
            setcookie('turnstile', '', time() - 3600);
            return false;
        }

        // validate the signature
        $addr = self::INCLUDE_CLIENT_ADDR ? '|' . ($_SERVER['REMOTE_ADDR'] ?? '') : '';
        $payload = ($token->uuid ?? '') . '|' . $expires . $addr;
        $signature = hash_hmac('sha256', $payload, $config['secret']);
        return hash_equals($signature, $token->signature ?? '');
    }

    public function processRequest() {
        $config = Engine::param('turnstile');
        if (!$config || !isset($config['sitekey']))
            return;

        $qs = SSOCommon::zkQSParams();
        $token = $qs['token'] ?? false;

        if($token) {
            // process turnstile token
            $uuid = sha1(uniqid(rand()));
            try {
                $client = new Client([
                    RequestOptions::HEADERS => [
                        'User-Agent' => Engine::UA
                    ]
                ]);
                $response = $client->post($config['siteverify_uri'], [
                    RequestOptions::FORM_PARAMS => [
                        'secret' => $config['secret'],
                        'response' => $token,
                        'remoteip' => $_SERVER['REMOTE_ADDR'] ?? '',
                        'idempotency_key' => $uuid,
                    ]
                ]);

                $json = json_decode($response->getBody()->getContents());
                if($json->success) {
                    // create cookie and redirect
                    $expires = time() + self::TTL_SECONDS;
                    $addr = self::INCLUDE_CLIENT_ADDR ? '|' . ($_SERVER['REMOTE_ADDR'] ?? '') : '';
                    $payload = $uuid . '|' . $expires . $addr;
                    $signature = hash_hmac('sha256', $payload, $config['secret']);
                    $cookie = base64_encode(json_encode([
                        'uuid' => $uuid,
                        'expires' => $expires,
                        'signature' => $signature,
                    ]));

                    // clear the test cookie
                    setcookie('testcookie', '', time() - 3600);

                    setcookie('turnstile', $cookie, [
                        'expires' => 0,
                        'path' => '/',
                        'domain' => $_SERVER['SERVER_NAME'],
                        'secure' => Engine::session()->isSecure(),
                        'httponly' => true,
                        'samesite' => 'lax'
                    ]);

                    $location = ($qs['location'] ?? '') ?: Engine::getBaseUrl();
                    SSOCommon::zkHttpRedirect($location, []);
                    exit;
                } else {
                    error_log("Turnstile validation failed: " . implode(', ', $json->{'error-codes'} ?? []));
                    $message = "Validation failed";
                }
            } catch (\Exception $e) {
                error_log("Turnstile connection error: " . $e->getMessage());
                $message = "There was a problem accessing the service.";
            }

            $templateName = 'turnstile/error.html';
            $params = [
                'message' => $message,
            ];
        } else {
            // check that cookies are enabled
            if($qs["checkCookie"] ?? false) {
                if(isset($_COOKIE["testcookie"])) {
                    // the cookie test was successful!

                    // do the Turnstile challenge
                    $templateName = 'turnstile/landing.html';
                    $params = [
                        'config' => $config,
                    ];
                } else {
                    // cookies are not enabled; alert user
                    $templateName = 'turnstile/cookies.html';
                    $params = [];
                }
            } else {
                // send a test cookie
                setcookie('testcookie', 'testcookie');
                $rq = [
                    'target' => 'turnstile',
                    'checkCookie' => 1,
                    'location' => $qs['location'] ?? '',
                ];
                $target = Engine::getBaseUrl();
                SSOCommon::zkHttpRedirect($target, $rq);
                exit;
            }
        }

        $templateFactory = new TemplateFactoryXML('html');
        $template = $templateFactory->load($templateName);
        header("Content-type: text/html; charset=UTF-8");
        echo $template->render($params);
    }
}
