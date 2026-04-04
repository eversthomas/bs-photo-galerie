<?php

declare(strict_types=1);

/** @var \BSPhotoGalerie\Core\Application $app */
/** @var string $csrfToken */
/** @var string $localVersion */
/** @var array{tag:string,name:string,body:string,html_url:string,zipball_url:string,published_at:string,source:string}|null $remote */
/** @var bool $updateAvailable */
/** @var string $repoUrl */
/** @var bool $gitAllowed */
/** @var bool $hasGitDir */
/** @var bool $canShell */
?>
<section class="admin-panel">
    <h1>Software-Update</h1>
    <p class="muted">Vergleich mit dem öffentlichen GitHub-Repository
        <a href="<?= htmlspecialchars($repoUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">eversthomas/bs-photo-galerie</a>.
        Der Check nutzt die GitHub-API (Cache ca. 10&nbsp;Min.). Optional: in <code>config/.env</code> <code>GITHUB_API_TOKEN=…</code> setzen, um Rate-Limits zu lockern.</p>

    <div class="update-status-grid">
        <div class="update-card">
            <h2 class="update-card-title">Lokale Version</h2>
            <p class="update-version"><?= htmlspecialchars($localVersion, ENT_QUOTES, 'UTF-8') ?></p>
            <p class="small muted">Aus der Datei <code class="mono">VERSION</code> im Projektroot.</p>
        </div>
        <div class="update-card">
            <h2 class="update-card-title">GitHub</h2>
            <?php if ($remote === null) : ?>
                <p class="muted">Keine Release-/Tag-Information abrufbar (API-Limit, Nicht-Erreichbarkeit oder noch keine Tags).</p>
            <?php else : ?>
                <p class="update-version"><?= htmlspecialchars($remote['tag'], ENT_QUOTES, 'UTF-8') ?></p>
                <p class="small muted">Quelle: <?= htmlspecialchars($remote['source'] === 'release' ? 'GitHub Release' : 'Git-Tags', ENT_QUOTES, 'UTF-8') ?></p>
                <?php if ($remote['html_url'] !== '') : ?>
                    <p><a href="<?= htmlspecialchars($remote['html_url'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">Release/Notizen auf GitHub</a></p>
                <?php endif; ?>
                <?php if ($remote['zipball_url'] !== '') : ?>
                    <p><a href="<?= htmlspecialchars($remote['zipball_url'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">ZIP dieser Version herunterladen</a></p>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($remote !== null) : ?>
        <?php if ($updateAvailable) : ?>
            <div class="flash flash-info" role="status">
                Es ist eine <strong>neuere</strong> Version auf GitHub verfügbar (<?= htmlspecialchars($remote['tag'], ENT_QUOTES, 'UTF-8') ?>).
            </div>
        <?php else : ?>
            <p class="muted">Ihre Installation ist laut Versionsvergleich auf dem gleichen Stand oder neuer.</p>
        <?php endif; ?>

        <?php if ($remote['body'] !== '') : ?>
            <details class="update-notes">
                <summary>Release-Text (GitHub)</summary>
                <pre class="update-body"><?= htmlspecialchars($remote['body'], ENT_QUOTES, 'UTF-8') ?></pre>
            </details>
        <?php endif; ?>
    <?php endif; ?>

    <h2 class="update-section-title">Automatisches Update (Git)</h2>
    <p class="muted small">
        Auf vielen Webhosting-Paketen (z.&nbsp;B. reines FTP) ist kein Git installiert oder <code>proc_open</code> ist gesperrt – dann funktioniert der Button unten nicht.
        Dann bitte <strong>ZIP von GitHub</strong> laden und manuell einspielen oder per SSH <code>git pull</code> / <code>composer install</code> ausführen.
    </p>
    <ul class="small muted update-checklist">
        <li>Umgebungsvariable <code class="mono">BSPHOTO_ALLOW_GIT_UPDATE=1</code> in <code>config/.env</code> setzen (Sicherheit: nur bei Bedarf).</li>
        <li>Installation muss ein <strong>Git-Klon</strong> des Repos sein (Ordner <code>.git</code> vorhanden: <?= $hasGitDir ? '<strong>ja</strong>' : '<strong>nein</strong>' ?>).</li>
        <li>PHP <code>proc_open</code>: <?= $canShell ? '<strong>verfügbar</strong>' : '<strong>gesperrt / nicht nutzbar</strong>' ?>.</li>
        <li>Aktuell erlaubt: <?= $gitAllowed ? '<strong>ja</strong>' : '<strong>nein</strong> (Variable nicht gesetzt)' ?>.</li>
    </ul>

    <?php
    $canAuto = $gitAllowed && $hasGitDir && $canShell && $remote !== null && $updateAvailable;
    ?>
    <?php if ($canAuto) : ?>
        <form method="post" action="<?= htmlspecialchars($app->url('/admin/update/apply'), ENT_QUOTES, 'UTF-8') ?>" class="admin-form wide update-apply-form" onsubmit="return confirm('Wirklich per Git auf <?= htmlspecialchars($remote['tag'], ENT_QUOTES, 'UTF-8') ?> aktualisieren? Lokale Änderungen am Code müssen zuvor gesichert sein.');">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="target_tag" value="<?= htmlspecialchars($remote['tag'], ENT_QUOTES, 'UTF-8') ?>">
            <label class="field field-inline">
                <input type="checkbox" name="confirm" value="1" required>
                <span>Ich bestätige: Ich habe ein Backup (Dateien + Datenbank) und möchte auf <strong><?= htmlspecialchars($remote['tag'], ENT_QUOTES, 'UTF-8') ?></strong> aktualisieren.</span>
            </label>
            <button type="submit" class="button-primary">Jetzt per Git &amp; Composer aktualisieren</button>
        </form>
    <?php elseif ($remote !== null && $updateAvailable) : ?>
        <p class="muted">Automatisches Update ist aktuell <strong>nicht möglich</strong>. Bitte ZIP-Link oben nutzen oder Server-Konfiguration anpassen.</p>
    <?php endif; ?>
</section>
