<?php

declare(strict_types=1);

/** @var \BSPhotoGalerie\Core\HttpContext $app */
/** @var string $csrfToken */
/** @var list<\BSPhotoGalerie\Models\Media> $items */
/** @var list<array{id:int,name:string,slug:string,sort_order:int}> $categories */
/** @var string $mediaPeriod */
/** @var string $mediaPeriodLabel */
/** @var array<string, string> $mediaPeriodLabels */

$orderIds = array_map(static fn ($m) => (string) $m->id, $items);
$orderCsv = implode(',', $orderIds);
$sortable = $mediaPeriod === 'all';
$gridClass = 'media-grid' . ($sortable ? ' media-grid-sortable' : ' media-grid-static');
?>
<section class="admin-panel">
    <div class="admin-toolbar">
        <h1 class="admin-toolbar-title">Medien</h1>
        <div class="admin-toolbar-actions">
            <a class="button-secondary admin-toolbar-action" href="<?= htmlspecialchars($app->url('/admin/categories'), ENT_QUOTES, 'UTF-8') ?>">Kategorien</a>
            <a class="button-primary admin-toolbar-action" href="<?= htmlspecialchars($app->url('/admin/media/upload'), ENT_QUOTES, 'UTF-8') ?>">Hochladen</a>
        </div>
    </div>

    <nav class="media-period-nav" aria-label="Zeitraumfilter">
        <?php foreach ($mediaPeriodLabels as $key => $label) : ?>
            <?php
            $active = $mediaPeriod === $key;
            $href = $key === 'all'
                ? $app->url('/admin/media')
                : $app->url('/admin/media?period=' . rawurlencode($key));
            ?>
            <a class="media-period-tab<?= $active ? ' is-active' : '' ?>"
               href="<?= htmlspecialchars($href, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></a>
        <?php endforeach; ?>
    </nav>
    <p class="muted small media-period-hint">
        Ansicht: <strong><?= htmlspecialchars($mediaPeriodLabel, ENT_QUOTES, 'UTF-8') ?></strong>
        <?php if (! $sortable) : ?>
            — Reihenfolge speichern nur unter „Alle“.
        <?php else : ?>
            — Karten am ⠿-Griff ziehen, dann „Reihenfolge speichern“. Checkboxen für Kategorie-Zuweisung; „Alle auswählen“ betrifft die sichtbaren Bilder.
        <?php endif; ?>
    </p>

    <?php if ($items === []) : ?>
        <p class="muted">In diesem Zeitraum keine Bilder. <a href="<?= htmlspecialchars($app->url('/admin/media/upload'), ENT_QUOTES, 'UTF-8') ?>">Hochladen</a> oder anderen Zeitraum wählen.</p>
    <?php else : ?>
        <?php if ($sortable) : ?>
            <form method="post" action="<?= htmlspecialchars($app->url('/admin/media/reorder'), ENT_QUOTES, 'UTF-8') ?>" class="reorder-bar" id="reorder-form">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="period" value="<?= htmlspecialchars($mediaPeriod, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="order" id="reorder-input" value="<?= htmlspecialchars($orderCsv, ENT_QUOTES, 'UTF-8') ?>">
                <button type="submit" class="button-secondary" id="reorder-save">Reihenfolge speichern</button>
                <span class="muted small reorder-hint" id="reorder-dirty" hidden>Geändert – bitte speichern.</span>
            </form>
        <?php endif; ?>

        <form method="post"
              action="<?= htmlspecialchars($app->url('/admin/media/bulk-category'), ENT_QUOTES, 'UTF-8') ?>"
              id="bulk-category-form"
              class="media-bulk-bar">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="period" value="<?= htmlspecialchars($mediaPeriod, ENT_QUOTES, 'UTF-8') ?>">
            <label class="field field-inline media-bulk-selectall">
                <input type="checkbox" id="bulk-select-all" title="Alle sichtbaren Bilder markieren">
                <span>Alle auswählen</span>
            </label>
            <span class="media-bulk-label">→ Kategorie:</span>
            <select name="bulk_category_id" class="media-bulk-select" aria-label="Zielkategorie">
                <option value="">— ohne Kategorie —</option>
                <?php foreach ($categories as $c) : ?>
                    <option value="<?= (int) $c['id'] ?>"><?= htmlspecialchars($c['name'], ENT_QUOTES, 'UTF-8') ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="button-secondary">Zuweisen</button>
        </form>

        <div class="<?= htmlspecialchars($gridClass, ENT_QUOTES, 'UTF-8') ?>" role="list" id="media-sortable" data-initial-order="<?= htmlspecialchars($orderCsv, ENT_QUOTES, 'UTF-8') ?>" data-sortable="<?= $sortable ? '1' : '0' ?>">
            <?php foreach ($items as $m) : ?>
                <article class="media-card" role="listitem" draggable="<?= $sortable ? 'true' : 'false' ?>" data-media-id="<?= (int) $m->id ?>">
                    <label class="media-select-wrap" title="Zur Mehrfachzuordnung">
                        <input type="checkbox"
                               form="bulk-category-form"
                               name="ids[]"
                               value="<?= (int) $m->id ?>"
                               class="media-select-cb">
                        <span class="sr-only">Bild <?= (int) $m->id ?> auswählen</span>
                    </label>
                    <?php if ($sortable) : ?>
                        <button type="button" class="media-drag-hint" aria-label="Verschieben" title="Ziehen zum Sortieren">⠿</button>
                    <?php endif; ?>
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
                        <span class="muted small"><?= (int) ($m->width ?? 0) ?>×<?= (int) ($m->height ?? 0) ?> · <?= htmlspecialchars($m->createdAt, ENT_QUOTES, 'UTF-8') ?></span>
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
