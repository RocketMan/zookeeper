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

namespace ZK\PushNotification;

use ZK\Engine\Engine;
use ZK\Controllers\IPushProxy;
use ZK\Controllers\Turnstile;

use React\Cache\CacheInterface;
use React\Dns\Model\Message;
use React\Dns\Resolver;
use React\Dns\Resolver\ResolverInterface;
use React\Promise;
use React\Promise\PromiseInterface;
use React\Promise\Timer;

use SPOA\Protocol\Arg;
use SPOA\Server\Connection;

/**
 * SPOA server for Turnstile validation at the edge
 *
 * To use, in the `config.php` configuration file, include the stanza:
 *
 *    'push_proxy' => [
 *        [
 *            'proxy' => ZK\PushNotification\SpoaTurnstile::class,
 *            'server' => '127.0.0.1:32082', // optional; default shown
 *            'debug' => false,              // optional; default shown
 *        ],
 *        ...more proxies...
 *    ],
 *
 * where:
 *    'proxy' -- this class or a derivative;
 *    'server' -- server address and port (optional)
 *    'debug' -- enable SPOA protocol debugging (optional)
 *
 * See INSTALLATION.md for details on installing and configuring push
 * notifications.
 */
class SpoaTurnstile implements IPushProxy {
    const DEFAULT_SPOA_SERVER = "127.0.0.1:32082";

    const DEFAULT_RESOLVER = "8.8.8.8";  // google DNS
    const RESOLVER_TIMEOUT = 0.5;        // in sec

    private const REQUIRED_PARAMS = [ 'cookie', 'src' ];

    protected array $config;
    protected ResolverInterface $resolver;

    protected static function result(bool $success): PromiseInterface {
        return Promise\resolve([
            "req.run" => Arg::bool(true),
            "req.result" => Arg::bool($success),
        ]);
    }

    public function __construct(
        protected \React\EventLoop\LoopInterface $loop,
        protected CacheInterface $lruCache,
        array $config, // NOT the `config` property; this is used only in the ctor
    ) {
        $server = new \React\Socket\Server($config['server'] ?? self::DEFAULT_SPOA_SERVER, $loop);
        $server->on('connection', function(\React\Socket\ConnectionInterface $conn) use ($config) {
            $spoaConnection = new Connection($conn, $config['debug'] ?? false);
            $spoaConnection->on('pre-validate', fn($args) => $this->onPreValidate($args));
        });
    }

    /**
     * asynchronous `gethostbyaddr` and `gethostbynamel` with timeout
     *
     * If given an IP address, returns the FQDN string; if given a
     * hostname, returns an array<string> of addresses.  Upon failure,
     * (e.g., no record found, timeout), returns false.
     *
     * @param string $name IP address or FQDN to resolve
     * @param float $timeout timeout in seconds (optional; default 1)
     * @param string|null $refAddr hint for IP lookup to use ipv4 or ipv6
     * @return PromiseInterface<string|array|false> host name or array of IP address on success or false on failure
     */
    protected function dnslookup(string $name, float $timeout = 1.0, ?string $refAddr = null): PromiseInterface {
        if (filter_var($name, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $octets = array_reverse(explode('.', $name));
            $queryName = implode('.', $octets) . '.in-addr.arpa';
            $promise = $this->resolver->resolveAll($queryName, Message::TYPE_PTR)->then(fn(array $ips) => $ips[0] ?? false);
        } elseif (filter_var($name, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $hex = unpack('H*', inet_pton($name))[1];
            $nibbles = array_reverse(str_split($hex));
            $queryName = implode('.', $nibbles) . '.ip6.arpa';
            $promise = $this->resolver->resolveAll($queryName, Message::TYPE_PTR)->then(fn(array $ips) => $ips[0] ?? false);
        } else {
            $promise = $this->resolver->resolveAll($name,
                $refAddr && filter_var($refAddr, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) ? Message::TYPE_AAAA : Message::TYPE_A);
        }

        // Wrap with timeout and map errors to `false`
        return Timer\timeout($promise, $timeout, $this->loop)->then(
            fn($result) => $result, // successful lookup: string or array
            fn() => false           // failure or timeout
        );
    }

    public function onPreValidate(array $args): PromiseInterface|array {
        foreach (self::REQUIRED_PARAMS as $param) {
            if (!key_exists($param, $args)) {
                error_log("pre-validate missing required argument");
                return [];
            }
        }

        // Nothing to do if turnstile is disabled
        if (!$this->config || !isset($this->config['secret']))
            return self::result(true);

        $addr = $args['src']->value;
        $cookie = urldecode($args['cookie']->value);
        if (!$cookie) {
            $domainPromise = $this->lruCache->get($addr)->then(
                function ($result) use ($addr) {
                    return $result ??
                    $this->dnslookup($addr, self::RESOLVER_TIMEOUT)->then(
                        function ($domain) use ($addr) {
                            if ($domain)
                                $this->lruCache->set($addr, $domain);
                            return $domain;
                        }
                    );
                }
            );

            return $domainPromise->then(
                function ($domain) use ($addr) {
                    $allowed = $domain ? array_filter($this->config['whitelist'] ?? [],
                            fn($suffix) => str_ends_with($domain, $suffix)) : false;
                    $whitelisted = !empty($allowed);
                    if (!$whitelisted)
                        return self::result(false);

                    // Forward-confirm reverse DNS (FCrDNS)
                    $addrsPromise = $this->lruCache->get($domain)->then(
                        function ($result) use ($domain, $addr) {
                            return $result
                            ? explode(',', $result)
                            : $this->dnslookup($domain,
                                    self::RESOLVER_TIMEOUT, $addr)->then(
                                function ($addrs) use ($domain) {
                                    if ($addrs)
                                        $this->lruCache->set($domain, implode(',', $addrs));
                                    return $addrs;
                                }
                            );
                        }
                    );

                    return $addrsPromise->then(function ($addrs) use ($addr, $domain) {
                        if (!$addrs || !in_array($addr, $addrs)) {
                            error_log("DNS mismatch: $addr $domain");
                            return self::result(false);
                        }

                        return self::result(true);
                    });
                }
            );
        }

        // If cookie present, decode & validate
        $token = json_decode(base64_decode($cookie));

        $expires = $token->expires ?? 0;
        if (time() > $expires)
            return self::result(false);

        $addrSuffix = Turnstile::INCLUDE_CLIENT_ADDR ? '|' . $addr : '';
        $payload = ($token->uuid ?? '') . '|' . $expires . $addrSuffix;
        $signature = hash_hmac('sha256', $payload, $this->config['secret']);
        return self::result(hash_equals($signature, $token->signature ?? ''));
    }

    public function connect() {
        $this->config = Engine::param('turnstile');

        $dns = $this->config['resolver'] ?? self::DEFAULT_RESOLVER;
        $factory = new Resolver\Factory();
        $this->resolver = $factory->create($dns, $this->loop);
    }
}
