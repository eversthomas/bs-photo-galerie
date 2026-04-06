<?php

declare(strict_types=1);

/** @var \BSPhotoGalerie\Core\HttpContext $app */
/** @var string $csrfToken */
/** @var \BSPhotoGalerie\Models\Media $media */
/** @var list<array{id:int,name:string,slug:string,sort_order:int}> $categories */
?>
<section class="admin-panel">
    <h1>Medium bearbeiten</h1>
    <div class="media-edit-preview">
        <img src="<?= htmlspecialchars($app->url('/thumb/' . $media->id), ENT_QUOTES, 'UTF-8') ?>" alt="" width="240" height="240" loading="lazy">
    </div>

    <form method="post" action="<?= htmlspecialchars($app->url('/admin/media/' . $media->id . '/update'), ENT_QUOTES, 'UTF-8') ?>" class="admin-form wide">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
        <label class="field">
            <span>Titel</span>
            <input name="title" type="text" maxlength="255" required value="<?= htmlspecialchars($media->title, ENT_QUOTES, 'UTF-8') ?>">
        </label>
        <label class="field">
            <span>Beschreibung</span>
            <textarea name="description" rows="5" class="textarea"><?= htmlspecialchars($media->description, ENT_QUOTES, 'UTF-8') ?></textarea>
        </label>
        <label class="field">
            <span>Kategorie</span>
            <select name="category_id">
                <option value="">— keine —</option>
                <?php foreach ($categories as $c) : ?>
                    <option value="<?= (int) $c['id'] ?>"<?= $media->categoryId === $c['id'] ? ' selected' : '' ?>><?= htmlspecialchars($c['name'], ENT_QUOTES, 'UTF-8') ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="field check">
            <input type="checkbox" name="is_visible" value="1"<?= $media->isVisible ? ' checked' : '' ?>>
            <span>In der Galerie sichtbar (sobald Frontend aktiv)</span>
        </label>
        <button type="submit" class="button-primary">Speichern</button>
    </form>
    <p class="small muted">
        <a href="<?= htmlspecialchars($app->url('/admin/media'), ENT_QUOTES, 'UTF-8') ?>">Zurück zur Medienliste</a>
        · Datei: <span class="mono"><?= htmlspecialchars($media->storagePath, ENT_QUOTES, 'UTF-8') ?></span>
    </p>
</section>
