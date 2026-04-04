<?php

declare(strict_types=1);

/** @var \BSPhotoGalerie\Core\Application $app */
/** @var string $csrfToken */
/** @var string $localVersion */
/** @var array{tag:string,name:string,body:string,html_url:string,zipball_url:string,published_at:string,source:string,git_mode:string,git_ref:string}|null $remote */
/** @var string|null $remoteError */
/** @var string|null $remoteDiagnostic */
/** @var bool $updateAvailable */
/** @var string $repoUrl */
/** @var bool $gitAllowed */
/** @var bool $hasGitDir */
/** @var bool $canShell */

$sourceLabel = static function (?array $r): string {
    if ($r === null) {
        return '';
    }

    return match ($r['source'] ?? '') {
        'release' => 'GitHub Release',
        'tag' => 'Git-Tags (Semver)',
        'branch_file' => 'Datei VERSION (Standard-Branch)',
        default => 'GitHub',
    };
};
?>
<section class="admin-panel">
    <h1>Software-Update</h1>
    <p class="muted">Vergleich mit dem öffentlichen GitHub-Repository
        <a href="<?= htmlspecialchars($repoUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">eversthomas/bs-photo-galerie</a>.
        Es wird zuerst ein Release, dann ein semver-Tag, sonst die <code class="mono">VERSION</code>-Datei auf dem Standard-Branch (meist <code class="mono">main</code>) gelesen — auch ohne manuelles Git-Tag. Der Check nutzt die GitHub-API (Cache ca. 10&nbsp;Min.). Optional: in <code>config/.env</code> <code>GITHUB_API_TOKEN=…</code> setzen, um Rate-Limits zu lockern.</p>

    <form method="post" action="<?= htmlspecialchars($app->url('/admin/update/refresh'), ENT_QUOTES, 'UTF-8') ?>" class="admin-form update-refresh-form">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
        <button type="submit" class="button-secondary small">GitHub-Cache leeren und neu prüfen</button>
        <span class="small muted">Sinnvoll nach einem Push oder wenn der Stand noch veraltet wirkt.</span>
    </form>

    <div class="update-status-grid">
        <div class="update-card">
            <h2 class="update-card-title">Lokale Version</h2>
            <p class="update-version"><?= htmlspecialchars($localVersion, ENT_QUOTES, 'UTF-8') ?></p>
            <p class="small muted">Aus der Datei <code class="mono">VERSION</code> im Projektroot. Für ein sichtbares Update muss die <code class="mono">VERSION</code> auf GitHub <strong>höher</strong> sein als lokal (z.&nbsp;B. 0.1.2 vs. 0.1.1).</p>
        </div>
        <div class="update-card">
            <h2 class="update-card-title">GitHub</h2>
            <?php if ($remote === null) : ?>
                <p class="muted"><?= htmlspecialchars((string) ($remoteError ?? 'Unbekannter Fehler beim GitHub-Abruf.'), ENT_QUOTES, 'UTF-8') ?></p>
                <?php if (is_string($remoteDiagnostic) && $remoteDiagnostic !== '') : ?>
                    <details class="update-notes">
                        <summary>Technische Details (Diagnose)</summary>
                        <pre class="update-body"><?= htmlspecialchars($remoteDiagnostic, ENT_QUOTES, 'UTF-8') ?></pre>
                    </details>
                <?php endif; ?>
            <?php else : ?>
                <p class="update-version"><?= htmlspecialchars($remote['tag'], ENT_QUOTES, 'UTF-8') ?></p>
                <p class="small muted">Quelle: <?= htmlspecialchars($sourceLabel($remote), ENT_QUOTES, 'UTF-8') ?>
                    <?php if ($remote['source'] === 'branch_file') : ?>
                        — Branch <code class="mono"><?= htmlspecialchars($remote['git_ref'], ENT_QUOTES, 'UTF-8') ?></code>
                    <?php endif; ?>
                </p>
                <?php if ($remote['html_url'] !== '') : ?>
                    <p><a href="<?= htmlspecialchars($remote['html_url'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">Auf GitHub ansehen</a></p>
                <?php endif; ?>
                <?php if ($remote['zipball_url'] !== '') : ?>
                    <p><a href="<?= htmlspecialchars($remote['zipball_url'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">ZIP herunterladen</a></p>
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
            <p class="muted">Ihre Installation ist laut Versionsvergleich auf dem gleichen Stand oder neuer. Wenn Sie gerade erst auf GitHub eingecheckt haben: <code class="mono">VERSION</code> dort erhöhen oder ein Release/Tag anlegen, dann Cache leeren.</p>
        <?php endif; ?>

        <?php if ($remote['body'] !== '') : ?>
            <details class="update-notes">
                <summary>Hinweis / Notizen (GitHub)</summary>
                <pre class="update-body"><?= htmlspecialchars($remote['body'], ENT_QUOTES, 'UTF-8') ?></pre>
            </details>
        <?php endif; ?>
    <?php endif; ?>

    <h2 class="update-section-title">Automatisches Update (Git)</h2>
    <?php if (! $hasGitDir) : ?>
        <div class="flash flash-info" role="note">
            <p><strong>Bei dieser Installation fehlt der Ordner <code class="mono">.git</code>.</strong> Das ist üblich, wenn die Software per FTP, Dateimanager oder ZIP auf den Server gelegt wurde — dabei wird kein Git-Verlauf mit hochgeladen. Ohne <code class="mono">.git</code> kann diese Oberfläche kein <code class="mono">git fetch</code> / Checkout ausführen; <code class="mono">proc_open</code> allein reicht nicht.</p>
            <p class="small muted" style="margin-bottom: 0"><strong>Praktisch:</strong> Aktualisieren Sie über den <strong>ZIP-Link</strong> oben (Dateien ersetzen, <code class="mono">config/.env</code> und <code class="mono">storage/</code> behalten) oder führen Sie auf dem Server per SSH ein <code class="mono">git clone</code> des Repos aus und migrieren Sie Konfiguration bzw. Uploads. Wenn ein echter Klon läuft, können Sie zusätzlich <code class="mono">BSPHOTO_ALLOW_GIT_UPDATE=1</code> in <code class="mono">config/.env</code> setzen.</p>
        </div>
    <?php elseif (! $gitAllowed) : ?>
        <div class="flash flash-info" role="note">
            <p><strong>Git-Arbeitsverzeichnis ist vorhanden, Web-Updates sind aber nicht freigeschaltet.</strong> In <code class="mono">config/.env</code> die Zeile <code class="mono">BSPHOTO_ALLOW_GIT_UPDATE=1</code> eintragen — nur wenn Sie bewusst möchten, dass die Verwaltungsoberfläche <code class="mono">git</code> ausführen darf.</p>
        </div>
    <?php endif; ?>
    <p class="muted small">
        Auf vielen Webhosting-Paketen ist kein Git auf dem Server nutzbar oder <code>proc_open</code> ist gesperrt — dann funktioniert der Button unten nicht.
        Alternativ: <strong>ZIP von GitHub</strong> laden und manuell einspielen oder per SSH <code>git pull</code> / <code>composer install</code>.
    </p>
    <ul class="small muted update-checklist">
        <li>Installation muss ein <strong>Git-Klon</strong> des Repos sein (Ordner <code>.git</code> vorhanden: <?= $hasGitDir ? '<strong>ja</strong>' : '<strong>nein</strong>' ?>).</li>
        <li>PHP <code>proc_open</code>: <?= $canShell ? '<strong>verfügbar</strong>' : '<strong>gesperrt / nicht nutzbar</strong>' ?>.</li>
        <li>Umgebungsvariable <code class="mono">BSPHOTO_ALLOW_GIT_UPDATE=1</code> in <code>config/.env</code> (Sicherheit: nur bei Bedarf): <?= $gitAllowed ? '<strong>ja</strong>' : '<strong>nein</strong>' ?>.</li>
    </ul>

    <?php
    $canAuto = $gitAllowed && $hasGitDir && $canShell && $remote !== null && $updateAvailable;
    $confirmMsg = $remote !== null && $updateAvailable
        ? (
            $remote['git_mode'] === 'branch'
            ? 'Wirklich Branch „' . $remote['git_ref'] . '“ (Version ' . $remote['tag'] . ') auschecken und hart auf origin zurücksetzen? Lokale Änderungen am Code müssen zuvor gesichert sein.'
            : 'Wirklich per Git auf ' . $remote['tag'] . ' aktualisieren? Lokale Änderungen am Code müssen zuvor gesichert sein.'
        )
        : '';
    ?>
    <?php if ($canAuto) : ?>
        <form method="post" action="<?= htmlspecialchars($app->url('/admin/update/apply'), ENT_QUOTES, 'UTF-8') ?>" class="admin-form wide update-apply-form" onsubmit="return confirm(<?= json_encode($confirmMsg, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?>);">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="target_tag" value="<?= htmlspecialchars($remote['tag'], ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="git_mode" value="<?= htmlspecialchars($remote['git_mode'], ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="git_ref" value="<?= htmlspecialchars($remote['git_ref'], ENT_QUOTES, 'UTF-8') ?>">
            <label class="field field-inline">
                <input type="checkbox" name="confirm" value="1" required>
                <span>Ich bestätige: Ich habe ein Backup (Dateien + Datenbank) und möchte auf <strong><?= htmlspecialchars($remote['name'], ENT_QUOTES, 'UTF-8') ?></strong> aktualisieren.</span>
            </label>
            <button type="submit" class="button-primary">Jetzt per Git &amp; Composer aktualisieren</button>
        </form>
    <?php elseif ($remote !== null && $updateAvailable) : ?>
        <div class="flash flash-info" role="status">
            <p><strong>Automatisches Update per Klick ist derzeit nicht möglich.</strong> Es fehlt noch:</p>
            <ul class="small update-missing-reasons">
                <?php if (! $hasGitDir) : ?>
                    <li>ein <strong>Git-Klon</strong> auf dem Server (Ordner <code class="mono">.git</code>)</li>
                <?php endif; ?>
                <?php if (! $canShell) : ?>
                    <li>PHP-<strong><code class="mono">proc_open</code></strong> (wird vom Hoster blockiert)</li>
                <?php endif; ?>
                <?php if (! $gitAllowed) : ?>
                    <li>die Freigabe <code class="mono">BSPHOTO_ALLOW_GIT_UPDATE=1</code> in <code class="mono">config/.env</code></li>
                <?php endif; ?>
            </ul>
            <p class="small muted" style="margin-bottom: 0">Bitte den <strong>ZIP-Link</strong> oben nutzen oder die Punkte auf dem Server erfüllen.</p>
        </div>
    <?php endif; ?>
</section>
