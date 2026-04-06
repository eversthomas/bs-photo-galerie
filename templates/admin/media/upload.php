<?php

declare(strict_types=1);

/** @var \BSPhotoGalerie\Core\HttpContext $app */
/** @var string $csrfToken */
/** @var list<array{id:int,name:string,slug:string}> $categories */
?>
<section class="admin-panel">
    <h1>Bilder hochladen</h1>
    <p class="muted">JPEG, PNG, WebP oder GIF. MIME-Prüfung über fileinfo, maximale Größe siehe Konfiguration (Standard 20&nbsp;MB). Doppelte Dateien (SHA-256) werden abgelehnt.</p>
    <p class="muted small">Server-Limits: <code>upload_max_filesize</code> und <code>post_max_size</code> in der PHP-Konfiguration müssen ausreichen.</p>

    <form method="post" action="<?= htmlspecialchars($app->url('/admin/media/upload'), ENT_QUOTES, 'UTF-8') ?>" enctype="multipart/form-data" class="admin-form upload-form">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
        <label class="field">
            <span>Dateien (mehrfach möglich)</span>
            <input name="images[]" type="file" accept="image/jpeg,image/png,image/webp,image/gif" multiple required>
        </label>
        <label class="field">
            <span>Titel (optional, sonst Dateiname)</span>
            <input name="title" type="text" maxlength="255" autocomplete="off" placeholder="z. B. Sommer 2026">
        </label>
        <?php if ($categories !== []) : ?>
            <label class="field">
                <span>Kategorie (optional)</span>
                <select name="category_id">
                    <option value="">— keine —</option>
                    <?php foreach ($categories as $c) : ?>
                        <option value="<?= (int) $c['id'] ?>"><?= htmlspecialchars($c['name'], ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        <?php endif; ?>
        <button type="submit" class="button-primary">Hochladen</button>
    </form>
    <p class="small muted"><a href="<?= htmlspecialchars($app->url('/admin/media'), ENT_QUOTES, 'UTF-8') ?>">Zurück zur Medienliste</a></p>
</section>
