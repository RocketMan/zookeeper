<?php
/**
 * Zookeeper Online
 *
 * @author Jim Mason <jmason@ibinx.com>
 * @copyright Copyright (C) 1997-2025 Jim Mason <jmason@ibinx.com>
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

class Challenge implements IController {
    const DIFFICULTY = 10;    // leading zero bits required
    const TTL_SECONDS = 180;  // challenge expires in 3 minutes
    const INCLUDE_CLIENT_ADDR = true;

    public static function validate($challenge) {
        // Nothing to do if challenge is disabled
        $secret = Engine::param('challenge_secret');
        if (!$secret)
            return true;

        $pow = json_decode(base64_decode($challenge), true);

        // 1. Check that the challenge is well-formed and has not expired
        $expires = $pow['expires'] ?? 0;
        if (time() > $expires)
            return false;

        // 2. Verify signature
        $challenge = $pow['challenge'] ?? '';
        $difficulty = $pow['difficulty'] ?? self::DIFFICULTY;
        $addr = self::INCLUDE_CLIENT_ADDR ? '|' . ($_SERVER['REMOTE_ADDDR'] ?? '') : '';
        $payload = $challenge . '|' . $expires . '|' . $difficulty . $addr;
        $sig = hash_hmac('sha256', $payload, $secret);

        if (!hash_equals($sig, $pow['signature'] ?? ''))
            return false;

        // 3. Validate the PoW
        $nonce = $pow['nonce'] ?? '';
        $hash = hash('sha256', $challenge . $nonce, true);
        $bytes = unpack('C*', $hash);

        $zeroBits = 0;
        foreach ($bytes as $b) {
            if ($b === 0) {
                $zeroBits += 8;
            } else {
                for ($i = 7; $i >= 0; $i--) {
                    if (($b >> $i) & 1)
                        return $zeroBits >= $difficulty;

                    $zeroBits++;
                }
            }

            if ($zeroBits >= $difficulty)
                return true;
        }

        return $zeroBits >= $difficulty;
    }

    public function processRequest() {
        $challenge = bin2hex(random_bytes(8));
        $expires   = time() + self::TTL_SECONDS;

        $secret = Engine::param('challenge_secret');
        $addr = self::INCLUDE_CLIENT_ADDR ? '|' . ($_SERVER['REMOTE_ADDDR'] ?? '') : '';
        $payload = $challenge . '|' . $expires . '|' . self::DIFFICULTY . $addr;
        $signature = hash_hmac('sha256', $payload, $secret);

        header('Content-Type: application/json');
        echo json_encode([
            "challenge"  => $secret ? $challenge : 0,
            "difficulty" => self::DIFFICULTY,
            "expires"    => $expires,
            "signature"  => $signature
        ]);
    }
}
