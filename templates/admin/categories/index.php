<?php

declare(strict_types=1);

/** @var \BSPhotoGalerie\Core\HttpContext $app */
/** @var string $csrfToken */
/** @var list<array{id:int,name:string,slug:string,sort_order:int,is_public:bool}> $items */
?>
<section class="admin-panel">
    <div class="admin-toolbar">
        <h1 class="admin-toolbar-title">Kategorien</h1>
        <a class="button-primary admin-toolbar-action" href="<?= htmlspecialchars($app->url('/admin/categories/create'), ENT_QUOTES, 'UTF-8') ?>">Neue Kategorie</a>
    </div>
    <?php if ($items === []) : ?>
        <p class="muted">Noch keine Kategorie. <a href="<?= htmlspecialchars($app->url('/admin/categories/create'), ENT_QUOTES, 'UTF-8') ?>">Anlegen</a></p>
    <?php else : ?>
        <table class="admin-table">
            <thead>
            <tr>
                <th>Name</th>
                <th>Slug</th>
                <th>Sortierung</th>
                <th>Sichtbarkeit</th>
                <th>Links</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($items as $c) : ?>
                <tr>
                    <td><?= htmlspecialchars($c['name'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td class="mono small"><?= htmlspecialchars($c['slug'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= (int) $c['sort_order'] ?></td>
                    <td class="small"><?= ! empty($c['is_public']) ? '<strong>öffentlich</strong>' : '<span class="muted">privat</span>' ?></td>
                    <td class="small">
                        <?php
                        $galUrl = $app->publicUrl('/galerie/kategorie/' . rawurlencode($c['slug']));
                        $diaUrl = $galUrl . '?diashow=1';
                        $isPublic = ! empty($c['is_public']);
                        ?>
                        <div class="category-link-row">
                            <a href="<?= htmlspecialchars($galUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">Galerie</a>
                            ·
                            <a href="<?= htmlspecialchars($diaUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">Diashow</a>
                        </div>
                        <label class="category-url-copy muted small">
                            <span class="sr-only">Öffentliche Galerie-URL zum Kopieren</span>
                            <input type="text" readonly class="mono category-url-field" value="<?= htmlspecialchars($galUrl, ENT_QUOTES, 'UTF-8') ?>" onclick="this.select();" title="Klicken zum Markieren, dann Strg/Cmd+C kopieren" aria-label="Öffentliche Galerie-URL">
                        </label>
                        <?php if (! $isPublic) : ?>
                            <p class="small muted" style="margin:0.25rem 0 0">Gäste werden zur Anmeldung weitergeleitet.</p>
                        <?php endif; ?>
                    </td>
                    <td class="admin-table-actions">
                        <a href="<?= htmlspecialchars($app->url('/admin/categories/' . $c['id'] . '/edit'), ENT_QUOTES, 'UTF-8') ?>">Bearbeiten</a>
                        <form method="post" action="<?= htmlspecialchars($app->url('/admin/categories/' . $c['id'] . '/delete'), ENT_QUOTES, 'UTF-8') ?>" class="inline-delete-form" onsubmit="return confirm('Kategorie wirklich löschen?');">
                            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                            <button type="submit" class="button-danger-text">Löschen</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
    <p class="small muted"><a href="<?= htmlspecialchars($app->url('/admin/media'), ENT_QUOTES, 'UTF-8') ?>">Zur Medienliste</a></p>
</section>
