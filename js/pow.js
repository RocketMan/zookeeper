//
// Zookeeper Online
//
// @author Jim Mason <jmason@ibinx.com>
// @copyright Copyright (C) 1997-2025 Jim Mason <jmason@ibinx.com>
// @link https://zookeeper.ibinx.com/
// @license GPL-3.0
//
// This code is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License, version 3,
// as published by the Free Software Foundation.
//
// This program is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License,
// version 3, along with this program.  If not, see
// http://www.gnu.org/licenses/
//

/*! Zookeeper Online (C) 1997-2025 Jim Mason <jmason@ibinx.com> | @source: https://zookeeper.ibinx.com/ | @license: magnet:?xt=urn:btih:1f739d935676111cfff4b4693e3816e664797050&dn=gpl-3.0.txt GPL-v3.0 */

// how often to refresh challenge (in ms)
const POW_MIN_VALIDITY = 2 * 60 * 1000;  // 2 minutes
const POW_CHALLENGE_TTL = 3 * 60 * 1000; // matches service TTL

let powCache = null;

function countLeadingZeroBits(bytes) {
    let zeroBits = 0;

    for (let b of bytes) {
        if (b === 0) {
            zeroBits += 8;
        } else {
            for (let i = 7; i >= 0; i--) {
                if ((b >> i) & 1) {
                    return zeroBits;
                }
                zeroBits++;
            }
        }
    }

    return zeroBits;
}

async function sha256Hex(str) {
    const data = new TextEncoder().encode(str);
    const digest = await crypto.subtle.digest("SHA-256", data);
    return new Uint8Array(digest);
}

async function solvePoW(challenge, difficulty) {
    let nonce = 0;

    while (true) {
        const bytes = await sha256Hex(challenge + nonce);
        const z = countLeadingZeroBits(bytes);

        if (z >= difficulty) {
            return nonce;
        }

        nonce++;
    }
}

async function getPoW() {
    const now = Date.now();

    // reuse cached proof if it remains valid
    if (powCache && now < (powCache.expires * 1000) - POW_MIN_VALIDITY)
        return powCache;

    // request new challenge from the service
    const challengeData = await $.getJSON("?target=challenge");

    const challenge  = challengeData.challenge;
    const difficulty = challengeData.difficulty;
    const expires    = challengeData.expires;
    const signature  = challengeData.signature;

    // solve the challenge (service indicates 0 if challenge is unneeded)
    const nonce = challenge ? await solvePoW(challenge, difficulty) : 0;

    // cache result for reuse
    powCache = {
        challenge,
        nonce,
        difficulty,
        expires,
        signature
    };

    return powCache;
}
