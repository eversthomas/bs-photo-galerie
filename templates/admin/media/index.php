<?php

declare(strict_types=1);

/** @var \BSPhotoGalerie\Core\Application $app */
/** @var string $csrfToken */
/** @var list<\BSPhotoGalerie\Models\Media> $items */
/** @var list<array{id:int,name:string,slug:string,sort_order:int}> $categories */

$orderIds = array_map(static fn ($m) => (string) $m->id, $items);
$orderCsv = implode(',', $orderIds);
?>
<section class="admin-panel">
    <div class="admin-toolbar">
        <h1 class="admin-toolbar-title">Medien</h1>
        <div class="admin-toolbar-actions">
            <a class="button-secondary admin-toolbar-action" href="<?= htmlspecialchars($app->url('/admin/categories'), ENT_QUOTES, 'UTF-8') ?>">Kategorien</a>
            <a class="button-primary admin-toolbar-action" href="<?= htmlspecialchars($app->url('/admin/media/upload'), ENT_QUOTES, 'UTF-8') ?>">Hochladen</a>
        </div>
    </div>
    <p class="muted small">Karten per <strong>Ziehen</strong> sortieren (am ⠿-Griff), dann „Reihenfolge speichern“. Mehrere Bilder mit der <strong>Checkbox</strong> wählen und unten einer Kategorie zuordnen.</p>

    <?php if ($items === []) : ?>
        <p class="muted">Noch keine Bilder. <a href="<?= htmlspecialchars($app->url('/admin/media/upload'), ENT_QUOTES, 'UTF-8') ?>">Jetzt hochladen</a></p>
    <?php else : ?>
        <form method="post" action="<?= htmlspecialchars($app->url('/admin/media/reorder'), ENT_QUOTES, 'UTF-8') ?>" class="reorder-bar" id="reorder-form">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="order" id="reorder-input" value="<?= htmlspecialchars($orderCsv, ENT_QUOTES, 'UTF-8') ?>">
            <button type="submit" class="button-secondary" id="reorder-save">Reihenfolge speichern</button>
            <span class="muted small reorder-hint" id="reorder-dirty" hidden>Geändert – bitte speichern.</span>
        </form>

        <form method="post"
              action="<?= htmlspecialchars($app->url('/admin/media/bulk-category'), ENT_QUOTES, 'UTF-8') ?>"
              id="bulk-category-form"
              class="media-bulk-bar">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
            <span class="media-bulk-label">Auswahl → Kategorie:</span>
            <select name="bulk_category_id" class="media-bulk-select" aria-label="Zielkategorie">
                <option value="">— ohne Kategorie —</option>
                <?php foreach ($categories as $c) : ?>
                    <option value="<?= (int) $c['id'] ?>"><?= htmlspecialchars($c['name'], ENT_QUOTES, 'UTF-8') ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="button-secondary">Zuweisen</button>
        </form>

        <div class="media-grid media-grid-sortable" role="list" id="media-sortable" data-initial-order="<?= htmlspecialchars($orderCsv, ENT_QUOTES, 'UTF-8') ?>">
            <?php foreach ($items as $m) : ?>
                <article class="media-card" role="listitem" draggable="true" data-media-id="<?= (int) $m->id ?>">
                    <label class="media-select-wrap" title="Zur Mehrfachzuordnung">
                        <input type="checkbox"
                               form="bulk-category-form"
                               name="ids[]"
                               value="<?= (int) $m->id ?>"
                               class="media-select-cb">
                        <span class="sr-only">Bild <?= (int) $m->id ?> auswählen</span>
                    </label>
                    <button type="button" class="media-drag-hint" aria-label="Verschieben" title="Ziehen zum Sortieren">⠿</button>
                    <?php if (! $m->isVisible) : ?>
                        <span class="media-badge-offline">Ausgeblendet</span>
                    <?php endif; ?>
                    <a class="media-thumb-link" href="<?= htmlspecialchars($app->url('/' . $m->storagePath), ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">
                        <img class="media-thumb" src="<?= htmlspecialchars($app->url('/thumb/' . $m->id), ENT_QUOTES, 'UTF-8') ?>"
                             width="200" height="200" loading="lazy" alt="">
                    </a>
                    <div class="media-meta">
                        <form method="post" action="<?= htmlspecialchars($app->url('/admin/media/inline-title'), ENT_QUOTES, 'UTF-8') ?>" class="inline-title-form">
                            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                            <input type="hidden" name="id" value="<?= (int) $m->id ?>">
                            <label class="sr-only" for="title-<?= (int) $m->id ?>">Titel</label>
                            <input id="title-<?= (int) $m->id ?>" class="inline-title-input" name="title" type="text" value="<?= htmlspecialchars($m->title !== '' ? $m->title : $m->filename, ENT_QUOTES, 'UTF-8') ?>" maxlength="255">
                            <button type="submit" class="button-link button-tiny">Titel speichern</button>
                        </form>
                        <span class="muted small"><?= (int) ($m->width ?? 0) ?>×<?= (int) ($m->height ?? 0) ?></span>
                        <div class="media-actions">
                            <a href="<?= htmlspecialchars($app->url('/admin/media/' . $m->id . '/edit'), ENT_QUOTES, 'UTF-8') ?>">Bearbeiten</a>
                            <form method="post" action="<?= htmlspecialchars($app->url('/admin/media/' . $m->id . '/delete'), ENT_QUOTES, 'UTF-8') ?>" class="inline-delete-form" onsubmit="return confirm('Dieses Medium endgültig löschen?');">
                                <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                <button type="submit" class="button-danger-text">Löschen</button>
                            </form>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
        <script src="<?= htmlspecialchars($app->url('/js/admin-media.js'), ENT_QUOTES, 'UTF-8') ?>" defer></script>
    <?php endif; ?>
</section>
