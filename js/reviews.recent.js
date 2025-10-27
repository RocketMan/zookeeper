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

/*! Zookeeper Online (C) 1997-2023 Jim Mason <jmason@ibinx.com> | @source: https://zookeeper.ibinx.com/ | @license: magnet:?xt=urn:btih:1f739d935676111cfff4b4693e3816e664797050&dn=gpl-3.0.txt GPL-v3.0 */

$().ready(function(){
    const ROWS_PER_PAGE = 20;
    const MAX_CONCURRENT = 3;
    const MIN_DELAY = 125;         // ~8 images per second
    const WINDOW_MS = 3000;
    const IMAGES_PER_WINDOW = 25;  // max requests per window

    var palette = [ '#bdd0c4', '#9ab7d3', '#f5d2d3', '#f7e1d3', '#dfccf1' ];

    let totalCount;
    let currentPage = 1;
    let currentSort = { key: "reviewed", direction: "desc" };
    let genresVisible = new Set();
    let queue = [];
    let loadTimestamps = [];
    let active = 0;

    function loadNextImage() {
        if (active >= MAX_CONCURRENT || queue.length === 0) return;

        const now = Date.now();
        loadTimestamps = loadTimestamps.filter(ts => now - ts < WINDOW_MS);
        if (loadTimestamps.length >= IMAGES_PER_WINDOW) {
            // too many image loads recently; wait and retry
            setTimeout(loadNext, 300);
            return;
        }

        loadTimestamps.push(now);
        active++;

        let img = queue.shift();
        img.onload = () => {
            img.style.opacity = 1;
            if (active > 0) active--;
            setTimeout(loadNextImage, 125);
        };
        img.onerror = () => {
            if (active > 0) active--;
            setTimeout(loadNextImage, 125);
        };
        img.src = img.dataset.lazysrc;
        img.removeAttribute('data-lazysrc');
    }

    function renderTable() {
        const start = (currentPage - 1) * ROWS_PER_PAGE;
        const end = start + ROWS_PER_PAGE;
        const sorted = [...reviews].filter((r) => genresVisible.has(r.genre)).sort((a, b) => {
            let valA = a[currentSort.key];
            let valB = b[currentSort.key];
            if (currentSort.key === "reviewed") {
                valA = new Date(valA);
                valB = new Date(valB);
            } else {
                valA = String(valA).toLowerCase();
                valB = String(valB).toLowerCase();
            }

            if (valA < valB) return currentSort.direction === "asc" ? -1 : 1;
            if (valA > valB) return currentSort.direction === "asc" ? 1 : -1;
            return 0;
        });

        totalCount = sorted.length;
        const pageItems = sorted.slice(start, end);
        const tbody = document.getElementById("review-body");
        tbody.innerHTML = '';
        queue.length = 0;
        active = 0;
        pageItems.forEach(review => {
            var row = document.createElement("tr");
            var html = '<td colspan=2>';
            if (review.album.albumart) {
                html += `
                  <div class='artwork' style='background-color: ${palette[Math.floor((Math.random() * palette.length))]}'><a href='?action=search&amp;s=byAlbumKey&amp;n=${review.album.tag}'><img data-lazysrc='${review.album.albumart}' alt='Album artwork'></a></div>`;
            }
            html += `
                  <div><a href='?action=search&amp;s=byAlbumKey&amp;n=${review.album.tag}'><strong>${review.title}</strong></a></div>
                  <div>${review.artist}</div>
                  <div class='meta'>${review.genre} | ${review.reviewer} | ${review.reviewed}</div>`;
            if (review.hashtags) {
                html += "<div class='album-hashtag-area'>";
                review.hashtags.forEach(hashtag => {
                    html += `
                            <a href='?action=search&amp;s=byHashtag&amp;n=${hashtag.name.slice(1)}'><span class='album-hashtag palette-${hashtag.index}'>${hashtag.name}</span></a>`;
                })
                html += '</div>';
            }
            html += `
                  <div class='excerpt'>${review.body}</div>
              </td>
              <td class='meta'>${review.genre}</td>
              <td class='meta'>${review.reviewer}</td>
              <td class='meta'>${review.reviewed}</td>
            `;
            row.innerHTML = html;
            tbody.appendChild(row);
        });

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
        renderPagination();
        updateSortIndicators();
    }

    function renderPagination() {
        const totalPages = Math.ceil(totalCount / ROWS_PER_PAGE);
        const container = $('#pagination');
        container.html('');

        if (totalPages < 2)
            return;

        let numchunks = 10;
        let start = (((currentPage - 1) / numchunks) | 0) * numchunks;
        if (start == 0) start = 1;
        let end = Math.min(totalPages, start + numchunks + 1);

        const ul = $('<ul>', {
            class: 'pagination'
        });

        for (let i = start; i <= end; i++) {
            if (i === currentPage)
                ul.append($('<li>').text(i));
            else {
                var a = $('<a>', {
                    class: 'nav',
                    href: '#'
                }).text(i).on('click', () => {
                    currentPage = i; /* safe with let scoping in ES6 */
                    renderTable();
                });
                ul.append($('<li>').append(a));
            }
        }

        if(end < totalPages)
            ul.append($('<li>').text('...'));

        container.append(ul);
    }

    function updateSortIndicators() {
        $('.recent-reviews th').each(function () {
            this.classList.remove("active", "desc");
            if (this.dataset.key === currentSort.key) {
                this.classList.add("active");
                if (currentSort.direction === "desc") {
                    this.classList.add("desc");
                }
            }
        });

        $('.sort select').val(currentSort.key).selectmenu('refresh');
    }

    $('.sort select').on('change selectmenuchange', function() {
        var key = $(this).val();
        if (currentSort.key === key) {
            currentSort.direction = currentSort.direction === "asc" ? "desc" : "asc";
        } else {
            currentSort.key = key;
            currentSort.direction = "asc";
        }
        renderTable();
    }).selectmenu();

    $('.recent-reviews th').each(function () {
        $(this).on('click', () => {
            const key = this.dataset.key;
            if (currentSort.key === key) {
                currentSort.direction = currentSort.direction === "asc" ? "desc" : "asc";
            } else {
                currentSort.key = key;
                currentSort.direction = "asc";
            }
            renderTable();
        });
    });

    renderTable();

    function setGenreVisibility(genre, showIt) {
        showIt ? genresVisible.add(genre) : genresVisible.delete(genre);
        currentPage = 1;
        renderTable();
    }

    let genreMap = {};
    reviews.forEach(function(review) {
        if (genreMap[review.genre] === undefined) {
            genreMap[review.genre] = 0;
            $(".review-categories span." + review.genre.replace(/ /g, '-')).removeClass('zk-hidden');
        }
        genreMap[review.genre]++;
    });

    $("span.review-categories").removeClass('zk-hidden');
    $("#review-count").text(` Found ${reviews.length} reviews.`);

    for (let [genre, count] of Object.entries(genreMap)) {
        $('span.' + genre.replace(/ /g, '-') + ' > span').text(`${genre} (${count})`);
    }

    let selectedDj = $('#djPicker').children("option:selected").val();
    let storageKey = 'ReviewCategories-' + selectedDj;
    let categoryStr = localStorage.getItem(storageKey);
    let categories = categoryStr ? JSON.parse(categoryStr) : {};

    $(".categoryPicker input").each(function(e) {
        let genre  = $(this).val();
        let isChecked = !(categories[genre] === false);
        setGenreVisibility(genre, isChecked)
        $(this).prop('checked', isChecked);
    });

    $("#djPicker").on('change selectmenuchange', function(e) {
        let selectedDj = $(this).children("option:selected").val();
        window.location.assign('?action=viewRecent&dj=' + selectedDj);
    }).selectmenu();

    $(".categoryPicker input").on('change', function(e) {
        let genre = $(this).val();
        let isChecked = $(this).prop('checked');
        setGenreVisibility(genre, isChecked);
        categories[genre] = isChecked;
        localStorage.setItem(storageKey, JSON.stringify(categories));
    });
});
