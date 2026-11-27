//
// Zookeeper Online
//
// @author Jim Mason <jmason@ibinx.com>
// @copyright Copyright (C) 1997-2026 Jim Mason <jmason@ibinx.com>
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

/*! Zookeeper Online (C) 1997-2026 Jim Mason <jmason@ibinx.com> | @source: https://zookeeper.ibinx.com/ | @license: magnet:?xt=urn:btih:1f739d935676111cfff4b4693e3816e664797050&dn=gpl-3.0.txt GPL-v3.0 */

const LazyLoader = (() => {
    const MAX_CONCURRENT = 3;
    const MIN_DELAY = 125;         // ~8 images per second
    const WINDOW_MS = 3000;
    const IMAGES_PER_WINDOW = 25;  // max 25 requests per 3 seconds

    let queue = [];
    let loadTimestamps = [];
    let active = 0;

    function loadNextImage() {
        if (active >= MAX_CONCURRENT || queue.length === 0) return;

        const now = Date.now();
        loadTimestamps = loadTimestamps.filter(ts => now - ts < WINDOW_MS);
        if (loadTimestamps.length >= IMAGES_PER_WINDOW) {
            // too many image loads recently; wait and retry
            setTimeout(loadNextImage, MIN_DELAY * 2);
            return;
        }

        loadTimestamps.push(now);
        active++;

        let img = queue.shift();
        img.onload = () => {
            img.style.opacity = 1;
            if (active > 0) active--;
            setTimeout(loadNextImage, MIN_DELAY);
        };
        img.onerror = () => {
            if (active > 0) active--;
            setTimeout(loadNextImage, MIN_DELAY);
        };
        img.src = img.dataset.lazysrc;
        img.removeAttribute('data-lazysrc');
    }

    function loadImages() {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    queue.push(entry.target);
                    observer.unobserve(entry.target);
                    loadNextImage();
                }
            });
        }, {
            rootMargin: '100px',
            threshold: 0.1
        });

        document.querySelectorAll('img[data-lazysrc]').forEach(img => observer.observe(img));
    }

    function reset() {
        queue.length = 0;
        active = 0;
    }

    return {
        loadImages,
        reset
    };
})();
