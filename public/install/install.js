/**
 * BS Photo Galerie – Installer (Vanilla JS, kein Build-Schritt)
 */
(function () {
    'use strict';

    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Enter') {
            return;
        }
        var el = e.target;
        if (!el || !el.form) {
            return;
        }
        if (el.tagName === 'TEXTAREA') {
            return;
        }
        // Verhindert doppelte Absendung bei Enter in einem mehrteiligen Flow nicht global
    });
})();
