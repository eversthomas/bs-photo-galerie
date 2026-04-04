/**
 * Drag & Drop Sortierung der Medienkarten (Vanilla JS).
 */
(function () {
    'use strict';

    var grid = document.getElementById('media-sortable');
    var input = document.getElementById('reorder-input');
    var dirty = document.getElementById('reorder-dirty');
    if (!grid || !input) {
        return;
    }

    var dragEl = null;

    function orderFromDom() {
        var cards = grid.querySelectorAll('.media-card[data-media-id]');
        var ids = [];
        for (var i = 0; i < cards.length; i++) {
            ids.push(cards[i].getAttribute('data-media-id'));
        }
        return ids.join(',');
    }

    function markDirty() {
        var init = grid.getAttribute('data-initial-order') || '';
        if (orderFromDom() !== init) {
            if (dirty) {
                dirty.hidden = false;
            }
        } else if (dirty) {
            dirty.hidden = true;
        }
        input.value = orderFromDom();
    }

    grid.addEventListener('dragstart', function (e) {
        var card = e.target.closest('.media-card');
        if (!card) {
            return;
        }
        dragEl = card;
        e.dataTransfer.effectAllowed = 'move';
        e.dataTransfer.setData('text/plain', card.getAttribute('data-media-id'));
        card.classList.add('media-card-dragging');
    });

    grid.addEventListener('dragend', function () {
        if (dragEl) {
            dragEl.classList.remove('media-card-dragging');
        }
        dragEl = null;
    });

    grid.addEventListener('dragover', function (e) {
        e.preventDefault();
        e.dataTransfer.dropEffect = 'move';
        if (!dragEl) {
            return;
        }
        var after = getDragAfterElement(grid, e.clientY);
        if (after == null) {
            grid.appendChild(dragEl);
        } else {
            grid.insertBefore(dragEl, after);
        }
        markDirty();
    });

    function getDragAfterElement(container, y) {
        var elements = [].slice.call(container.querySelectorAll('.media-card:not(.media-card-dragging)'));
        return elements.reduce(
            function (closest, child) {
                var box = child.getBoundingClientRect();
                var offset = y - box.top - box.height / 2;
                if (offset < 0 && offset > closest.offset) {
                    return { offset: offset, element: child };
                }
                return closest;
            },
            { offset: Number.NEGATIVE_INFINITY, element: null }
        ).element;
    }

    grid.addEventListener('drop', function (e) {
        e.preventDefault();
        markDirty();
    });

    markDirty();
})();
