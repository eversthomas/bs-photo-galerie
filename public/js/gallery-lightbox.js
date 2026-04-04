/**
 * Galerie-Lightbox: Lightbox, Diashow (Timer), Vollbild, optionale Musik (Vanilla JS).
 */
(function () {
    'use strict';

    var STORAGE_INTERVAL = 'bsphoto_slideshow_interval_sec';

    function readConfig() {
        var el = document.getElementById('gallery-runtime-config');
        if (!el || !el.textContent.trim()) {
            return {
                slideshowEnabled: false,
                slideshowInterval: 5,
                musicEnabled: false,
                musicUrls: [],
            };
        }
        try {
            return JSON.parse(el.textContent);
        } catch (e) {
            return {
                slideshowEnabled: false,
                slideshowInterval: 5,
                musicEnabled: false,
                musicUrls: [],
            };
        }
    }

    var config = readConfig();
    var deepLinkDiashow = false;
    try {
        deepLinkDiashow = new URLSearchParams(window.location.search).get('diashow') === '1';
    } catch (e) {}

    function slideshowAllowed() {
        return (config.slideshowEnabled || deepLinkDiashow) && items.length > 1;
    }

    var dialog = document.getElementById('gallery-lightbox');
    var fsRoot = document.getElementById('lb-fs-root');
    var grid = document.getElementById('gallery-grid');
    if (!dialog || !grid) {
        return;
    }

    var lbImg = document.getElementById('lb-img');
    var lbTitle = document.getElementById('lb-title');
    var lbDesc = document.getElementById('lb-desc');
    var btnPrev = document.getElementById('lb-prev');
    var btnNext = document.getElementById('lb-next');
    var btnSlideshow = document.getElementById('lb-slideshow');
    var selInterval = document.getElementById('lb-interval');
    var btnMusic = document.getElementById('lb-music');
    var btnFs = document.getElementById('lb-fullscreen');
    var groupSlideshow = document.getElementById('lb-group-slideshow');
    var groupMusic = document.getElementById('lb-group-music');
    var audio = document.getElementById('gallery-bg-audio');

    var fauxFullscreenActive = false;

    function getFullscreenElement() {
        return (
            document.fullscreenElement ||
            document.webkitFullscreenElement ||
            document.mozFullScreenElement ||
            document.msFullscreenElement ||
            null
        );
    }

    function requestFullscreenEl(el) {
        if (!el) {
            return Promise.reject();
        }
        if (typeof el.requestFullscreen === 'function') {
            return el.requestFullscreen();
        }
        if (typeof el.webkitRequestFullscreen === 'function') {
            return el.webkitRequestFullscreen();
        }
        if (typeof el.webkitRequestFullScreen === 'function') {
            return el.webkitRequestFullScreen();
        }
        if (typeof el.msRequestFullscreen === 'function') {
            return el.msRequestFullscreen();
        }

        return Promise.reject();
    }

    function exitFullscreenDoc() {
        var d = document;
        if (typeof d.exitFullscreen === 'function' && getFullscreenElement()) {
            return d.exitFullscreen();
        }
        if (typeof d.webkitExitFullscreen === 'function' && d.webkitFullscreenElement) {
            return d.webkitExitFullscreen();
        }
        if (typeof d.mozCancelFullScreen === 'function' && d.mozFullScreenElement) {
            return d.mozCancelFullScreen();
        }
        if (typeof d.msExitFullscreen === 'function' && d.msFullscreenElement) {
            return d.msExitFullscreen();
        }

        return Promise.resolve();
    }

    function setFsButtonState(on) {
        if (!btnFs) {
            return;
        }
        btnFs.setAttribute('aria-pressed', on ? 'true' : 'false');
        btnFs.textContent = on ? 'Vollbild beenden' : 'Vollbild';
        btnFs.setAttribute('title', on && fauxFullscreenActive ? 'Randlos beenden (ohne Browser-Vollbild)' : 'Vollbildmodus');
    }

    function isFsUiActive() {
        if (fauxFullscreenActive) {
            return true;
        }
        return dialog.open && getFullscreenElement() !== null;
    }

    function syncFsButtonFromDom() {
        if (!dialog.open) {
            return;
        }
        setFsButtonState(isFsUiActive());
    }

    var items = [];
    var current = 0;
    var slideshowTimer = null;
    var playlistIndex = 0;
    var musicPlaying = false;

    var intervalChoices = [3, 5, 8, 10, 15, 30, 60];

    function collectItems() {
        items = [];
        var tiles = grid.querySelectorAll('.gallery-tile');
        for (var i = 0; i < tiles.length; i++) {
            var t = tiles[i];
            items.push({
                full: t.getAttribute('data-full') || '',
                title: t.getAttribute('data-title') || '',
                desc: t.getAttribute('data-desc') || '',
            });
        }
    }

    function currentIntervalSec() {
        if (selInterval && selInterval.value) {
            var n = parseInt(selInterval.value, 10);
            if (!isNaN(n) && n >= 3) {
                return n;
            }
        }
        return config.slideshowInterval || 5;
    }

    function show(i) {
        if (items.length === 0) {
            return;
        }
        current = ((i % items.length) + items.length) % items.length;
        var it = items[current];
        lbImg.src = it.full;
        lbImg.alt = it.title || 'Bild';
        lbTitle.textContent = it.title || '';
        lbDesc.textContent = it.desc || '';
        lbDesc.style.display = it.desc ? 'block' : 'none';
        restartSlideshowIfPlaying();
    }

    function openAt(index) {
        collectItems();
        if (items.length === 0) {
            return;
        }
        show(parseInt(index, 10) || 0);
        if (typeof dialog.showModal === 'function') {
            dialog.showModal();
        }
    }

    function clearImage() {
        lbImg.removeAttribute('src');
    }

    function close() {
        if (typeof dialog.close === 'function') {
            dialog.close();
        }
    }

    function stopSlideshow() {
        if (slideshowTimer !== null) {
            clearInterval(slideshowTimer);
            slideshowTimer = null;
        }
        if (btnSlideshow) {
            btnSlideshow.setAttribute('aria-pressed', 'false');
            btnSlideshow.textContent = 'Diashow';
        }
    }

    function restartSlideshowIfPlaying() {
        if (slideshowTimer === null) {
            return;
        }
        stopSlideshow();
        startSlideshow();
    }

    function startSlideshow() {
        stopSlideshow();
        if (!slideshowAllowed()) {
            return;
        }
        var ms = currentIntervalSec() * 1000;
        slideshowTimer = setInterval(function () {
            show(current + 1);
        }, ms);
        if (btnSlideshow) {
            btnSlideshow.setAttribute('aria-pressed', 'true');
            btnSlideshow.textContent = 'Pause';
        }
    }

    function toggleSlideshow() {
        if (!slideshowAllowed()) {
            return;
        }
        if (slideshowTimer !== null) {
            stopSlideshow();
        } else {
            startSlideshow();
        }
    }

    function loadAudioTrack(idx) {
        if (!audio || !config.musicUrls.length) {
            return;
        }
        playlistIndex = ((idx % config.musicUrls.length) + config.musicUrls.length) % config.musicUrls.length;
        audio.src = config.musicUrls[playlistIndex];
    }

    function pauseMusic() {
        if (!audio) {
            return;
        }
        audio.pause();
        musicPlaying = false;
        if (btnMusic) {
            btnMusic.setAttribute('aria-pressed', 'false');
            btnMusic.textContent = 'Musik';
        }
    }

    function tryPlayMusic() {
        if (!audio || !config.musicUrls.length || !config.musicEnabled) {
            return;
        }
        loadAudioTrack(playlistIndex);
        audio.play().then(
            function () {
                musicPlaying = true;
                if (btnMusic) {
                    btnMusic.setAttribute('aria-pressed', 'true');
                    btnMusic.textContent = 'Musik aus';
                }
            },
            function () {
                musicPlaying = false;
            }
        );
    }

    function toggleMusic() {
        if (!config.musicEnabled || !config.musicUrls.length) {
            return;
        }
        if (musicPlaying) {
            pauseMusic();
        } else {
            tryPlayMusic();
        }
    }

    function onAudioEnded() {
        if (!config.musicUrls.length) {
            return;
        }
        loadAudioTrack(playlistIndex + 1);
        audio.play().catch(function () {});
    }

    function setupUiFromConfig() {
        var showMusic = config.musicEnabled && config.musicUrls.length > 0;
        if (groupMusic) {
            groupMusic.hidden = !showMusic;
        }
        if (groupSlideshow && btnSlideshow && selInterval) {
            var multi = items.length > 1;
            groupSlideshow.hidden = !multi;
            selInterval.disabled = !multi;
            btnSlideshow.disabled = !slideshowAllowed();
            if (btnSlideshow) {
                btnSlideshow.title = slideshowAllowed()
                    ? 'Diashow starten oder pausieren'
                    : 'Diashow ist in der Verwaltung unter Einstellungen deaktiviert (oder nur ein Bild). Mit ?diashow=1 in der URL testen.';
            }
        }
        if (selInterval && selInterval.options.length === 0) {
            for (var j = 0; j < intervalChoices.length; j++) {
                var sec = intervalChoices[j];
                var opt = document.createElement('option');
                opt.value = String(sec);
                opt.textContent = sec + ' s';
                selInterval.appendChild(opt);
            }
            var def = config.slideshowInterval;
            try {
                var stored = sessionStorage.getItem(STORAGE_INTERVAL);
                if (stored) {
                    def = parseInt(stored, 10) || def;
                }
            } catch (e) {}
            if (selInterval.querySelector('option[value="' + def + '"]')) {
                selInterval.value = String(def);
            } else {
                selInterval.value = String(config.slideshowInterval);
            }
        }
    }

    function cleanupOnClose() {
        clearImage();
        stopSlideshow();
        pauseMusic();
        fauxFullscreenActive = false;
        if (fsRoot) {
            fsRoot.classList.remove('lb-faux-fullscreen');
        }
        if (getFullscreenElement()) {
            exitFullscreenDoc().catch(function () {});
        }
        setFsButtonState(false);
    }

    dialog.addEventListener('close', cleanupOnClose);

    grid.addEventListener('click', function (e) {
        var tile = e.target.closest('.gallery-tile');
        if (!tile || !grid.contains(tile)) {
            return;
        }
        collectItems();
        setupUiFromConfig();
        var idx = parseInt(tile.getAttribute('data-index'), 10);
        if (!isNaN(idx)) {
            openAt(idx);
        }
    });

    if (btnPrev) {
        btnPrev.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            show(current - 1);
        });
    }
    if (btnNext) {
        btnNext.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            show(current + 1);
        });
    }

    if (btnSlideshow) {
        btnSlideshow.addEventListener('click', function () {
            toggleSlideshow();
        });
    }

    if (selInterval) {
        selInterval.addEventListener('change', function () {
            try {
                sessionStorage.setItem(STORAGE_INTERVAL, selInterval.value);
            } catch (e) {}
            restartSlideshowIfPlaying();
        });
    }

    if (btnMusic) {
        btnMusic.addEventListener('click', function () {
            toggleMusic();
        });
    }

    if (btnFs) {
        btnFs.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            if (fauxFullscreenActive) {
                fauxFullscreenActive = false;
                if (fsRoot) {
                    fsRoot.classList.remove('lb-faux-fullscreen');
                }
                setFsButtonState(false);
                return;
            }
            if (getFullscreenElement()) {
                exitFullscreenDoc()
                    .then(function () {
                        setFsButtonState(false);
                    })
                    .catch(function () {
                        setFsButtonState(false);
                    });
                return;
            }
            var candidates = [fsRoot, dialog, document.documentElement].filter(function (n) {
                return !!n;
            });
            (function tryEnter(i) {
                if (i >= candidates.length) {
                    if (fsRoot) {
                        fauxFullscreenActive = true;
                        fsRoot.classList.add('lb-faux-fullscreen');
                    }
                    setFsButtonState(true);
                    return;
                }
                requestFullscreenEl(candidates[i])
                    .then(function () {
                        setFsButtonState(true);
                    })
                    .catch(function () {
                        tryEnter(i + 1);
                    });
            })(0);
        });
    }

    document.addEventListener('fullscreenchange', syncFsButtonFromDom);
    document.addEventListener('webkitfullscreenchange', syncFsButtonFromDom);
    document.addEventListener('mozfullscreenchange', syncFsButtonFromDom);
    document.addEventListener('MSFullscreenChange', syncFsButtonFromDom);

    if (audio) {
        audio.addEventListener('ended', onAudioEnded);
    }

    dialog.querySelectorAll('[data-lb-close]').forEach(function (el) {
        el.addEventListener('click', function () {
            close();
        });
    });

    function onKey(e) {
        if (!dialog.open) {
            return;
        }
        if (e.key === 'Escape') {
            if (getFullscreenElement() !== null) {
                return;
            }
            if (fauxFullscreenActive) {
                fauxFullscreenActive = false;
                if (fsRoot) {
                    fsRoot.classList.remove('lb-faux-fullscreen');
                }
                setFsButtonState(false);
                return;
            }
            close();
        } else if (e.key === 'ArrowRight') {
            show(current + 1);
        } else if (e.key === 'ArrowLeft') {
            show(current - 1);
        }
    }

    document.addEventListener('keydown', onKey);

    var touchStartX = null;

    dialog.addEventListener(
        'touchstart',
        function (e) {
            if (!dialog.open) {
                return;
            }
            touchStartX = e.changedTouches[0].screenX;
        },
        { passive: true }
    );

    dialog.addEventListener(
        'touchend',
        function (e) {
            if (!dialog.open || touchStartX === null) {
                return;
            }
            var dx = e.changedTouches[0].screenX - touchStartX;
            touchStartX = null;
            if (Math.abs(dx) < 56) {
                return;
            }
            if (dx < 0) {
                show(current + 1);
            } else {
                show(current - 1);
            }
        },
        { passive: true }
    );

    if (deepLinkDiashow) {
        collectItems();
        if (items.length > 0) {
            setupUiFromConfig();
            openAt(0);
            if (items.length > 1) {
                startSlideshow();
            }
        }
    }
})();
