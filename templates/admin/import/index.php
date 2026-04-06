<?php

declare(strict_types=1);

/** @var \BSPhotoGalerie\Core\HttpContext $app */
/** @var string $csrfToken */
/** @var string $importPath */
/** @var string $importPathRelative */
/** @var bool $ftpConfigured */
/** @var list<array{id:int,name:string,slug:string}> $categories */
?>
<section class="admin-panel">
    <h1>Import</h1>
    <p class="muted">
        Lege Bilder in den Ordner <code><?= htmlspecialchars($importPathRelative, ENT_QUOTES, 'UTF-8') ?></code> ab
        (absolut: <span class="mono small"><?= htmlspecialchars($importPath, ENT_QUOTES, 'UTF-8') ?></span>), z. B. per FTP‑Client in dieses Verzeichnis synchronisieren.
        Beim Lauf werden unterstützte Bilder nach <code>public/uploads/</code> kopiert, mit Hash/EXIF/Vorschaubild verarbeitet und die Quelle optional gelöscht.
    </p>

    <form method="post" action="<?= htmlspecialchars($app->url('/admin/import/run'), ENT_QUOTES, 'UTF-8') ?>" class="admin-form import-form">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

        <?php if ($ftpConfigured) : ?>
            <label class="field check">
                <input type="checkbox" name="pull_ftp" value="1">
                <span>Zuerst per FTP aus dem konfigurierten Server-Verzeichnis holen (flach, keine Unterordner auf dem FTP)</span>
            </label>
        <?php else : ?>
            <p class="muted small">FTP-Import ist in <code>config.php</code> unter <code>import.ftp</code> deaktiviert oder unvollständig.</p>
        <?php endif; ?>

        <label class="field check">
            <input type="checkbox" name="remove_missing" value="1">
            <span>Datenbank bereinigen: Einträge löschen, wenn die Datei unter <code>public/uploads/</code> fehlt (inkl. Vorschaubild)</span>
        </label>

        <?php if ($categories !== []) : ?>
            <label class="field">
                <span>Kategorie für neue Bilder (optional)</span>
                <select name="category_id">
                    <option value="">— keine —</option>
                    <?php foreach ($categories as $c) : ?>
                        <option value="<?= (int) $c['id'] ?>"><?= htmlspecialchars($c['name'], ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        <?php endif; ?>

        <button type="submit" class="button-primary">Import starten</button>
    </form>

    <p class="small muted">
        <a href="<?= htmlspecialchars($app->url('/admin/media'), ENT_QUOTES, 'UTF-8') ?>">Zur Medienliste</a>
        · CLI: <code class="mono">php bin/import.php</code> (Optionen: <code class="mono">--ftp</code> <code class="mono">--prune</code> <code class="mono">--category=ID</code>)
    </p>
</section>
