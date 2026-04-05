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
/** @var bool $webUpdateAllowed */
/** @var bool $hasZipExtension */
/** @var bool $hasGitDir */
/** @var bool $canShell */
/** @var bool $canZipUpdate */

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

$canGitUpdate = $webUpdateAllowed && $hasGitDir && $canShell && $remote !== null && $updateAvailable;
?>
<section class="admin-panel">
    <h1>Software-Update</h1>
    <p class="muted">Vergleich mit dem öffentlichen GitHub-Repository
        <a href="<?= htmlspecialchars($repoUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">eversthomas/bs-photo-galerie</a>.
        Es wird zuerst ein Release, dann ein semver-Tag, sonst die <code class="mono">VERSION</code>-Datei auf dem Standard-Branch gelesen. Der Check nutzt die GitHub-API (Cache ca. 10&nbsp;Min.). Optional: <code>config/.env</code> <code>GITHUB_API_TOKEN=…</code>.</p>

    <p class="small muted"><strong>Update aus der Verwaltung:</strong> Ohne <code class="mono">.git</code> lädt die Software das <strong>offizielle GitHub-ZIP</strong> und ersetzt Projektdateien (Konfiguration und Uploads bleiben erhalten). Mit Git-Klon weiterhin <strong>Git + Checkout</strong>. Freischaltung: <code class="mono">BSPHOTO_ALLOW_WEB_UPDATE=1</code> oder die bisherige <code class="mono">BSPHOTO_ALLOW_GIT_UPDATE=1</code> in <code class="mono">config/.env</code> — nur setzen, wenn Sie die Risiken (Schreibzugriff auf den Code) bewusst eingehen.</p>

    <form method="post" action="<?= htmlspecialchars($app->url('/admin/update/refresh'), ENT_QUOTES, 'UTF-8') ?>" class="admin-form update-refresh-form">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
        <button type="submit" class="button-secondary small">GitHub-Cache leeren und neu prüfen</button>
        <span class="small muted">Sinnvoll nach einem Push oder wenn der Stand noch veraltet wirkt.</span>
    </form>

    <div class="update-status-grid">
        <div class="update-card">
            <h2 class="update-card-title">Lokale Version</h2>
            <p class="update-version"><?= htmlspecialchars($localVersion, ENT_QUOTES, 'UTF-8') ?></p>
            <p class="small muted">Aus der Datei <code class="mono">VERSION</code> im Projektroot. Ein Update erscheint, wenn die <code class="mono">VERSION</code> auf GitHub <strong>höher</strong> ist als lokal.</p>
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
                    <p><a href="<?= htmlspecialchars($remote['zipball_url'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">ZIP manuell herunterladen</a></p>
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
                <summary>Hinweis / Notizen (GitHub)</summary>
                <pre class="update-body"><?= htmlspecialchars($remote['body'], ENT_QUOTES, 'UTF-8') ?></pre>
            </details>
        <?php endif; ?>
    <?php endif; ?>

    <h2 class="update-section-title">Automatisches Update</h2>

    <?php if (! $webUpdateAllowed) : ?>
        <div class="flash flash-info" role="note">
            <p><strong>Web-Update ist nicht freigeschaltet.</strong> Tragen Sie in <code class="mono">config/.env</code> eine der Zeilen ein:</p>
            <p class="small mono" style="margin-bottom: 0">BSPHOTO_ALLOW_WEB_UPDATE=1</p>
            <p class="small muted" style="margin-bottom: 0">(Die ältere Variable <code class="mono">BSPHOTO_ALLOW_GIT_UPDATE=1</code> gilt weiterhin als Freigabe.)</p>
        </div>
    <?php endif; ?>

    <ul class="small muted update-checklist">
        <li>Web-Update freigeschaltet: <?= $webUpdateAllowed ? '<strong>ja</strong>' : '<strong>nein</strong>' ?> (siehe <code class="mono">.env</code>)</li>
        <li>Git-Arbeitsverzeichnis (<code class="mono">.git</code>): <?= $hasGitDir ? '<strong>ja</strong> — optional Git-Update' : '<strong>nein</strong> — ZIP-Update' ?></li>
        <li>PHP <code>proc_open</code> (für <code>composer install</code>): <?= $canShell ? '<strong>verfügbar</strong>' : '<strong>gesperrt</strong>' ?></li>
        <li>PHP-Erweiterung <code>zip</code> (ZipArchive): <?= $hasZipExtension ? '<strong>ja</strong>' : '<strong>nein</strong> (ZIP-Update nicht möglich)' ?></li>
    </ul>

    <?php
    $zipConfirm = '';
    $gitConfirm = '';
    if ($remote !== null) {
        $zipConfirm = 'Wirklich per GitHub-ZIP auf ' . $remote['tag'] . ' aktualisieren? Ihre Dateien config/.env, config/config.php, storage/ und public/uploads/ bleiben erhalten. Vorher Backup (Dateien + Datenbank).';
        $gitConfirm = $remote['git_mode'] === 'branch'
            ? 'Wirklich per Git Branch „' . $remote['git_ref'] . '“ (Version ' . $remote['tag'] . ') aktualisieren? Lokale Code-Änderungen gehen verloren.'
            : 'Wirklich per Git auf ' . $remote['tag'] . ' aktualisieren? Lokale Code-Änderungen gehen verloren.';
    }
    ?>

    <?php if ($remote !== null && $updateAvailable && $canZipUpdate) : ?>
        <h3 class="update-section-title" style="font-size: 1rem; margin-top: 1.25rem;">Per GitHub-ZIP (empfohlen ohne .git)</h3>
        <p class="small muted">Lädt das Archiv von GitHub, entpackt und überschreibt Projektdateien, führt <code class="mono">composer install</code> aus. Geschützt bleiben: <code class="mono">config/.env</code>, <code class="mono">config/config.php</code>, der Ordner <code class="mono">storage/</code> und <code class="mono">public/uploads/</code>.</p>
        <form method="post" action="<?= htmlspecialchars($app->url('/admin/update/apply'), ENT_QUOTES, 'UTF-8') ?>" class="admin-form wide update-apply-form" onsubmit="return confirm(<?= json_encode($zipConfirm, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?>);">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="channel" value="zip">
            <input type="hidden" name="target_tag" value="<?= htmlspecialchars($remote['tag'], ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="git_mode" value="<?= htmlspecialchars($remote['git_mode'], ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="git_ref" value="<?= htmlspecialchars($remote['git_ref'], ENT_QUOTES, 'UTF-8') ?>">
            <label class="field field-inline">
                <input type="checkbox" name="confirm" value="1" required>
                <span>Ich bestätige: Backup liegt vor. Ich möchte per <strong>ZIP</strong> auf <strong><?= htmlspecialchars($remote['name'], ENT_QUOTES, 'UTF-8') ?></strong> aktualisieren.</span>
            </label>
            <button type="submit" class="button-primary">Jetzt per GitHub-ZIP aktualisieren</button>
        </form>
    <?php elseif ($remote !== null && $updateAvailable && $webUpdateAllowed && ! $canZipUpdate) : ?>
        <div class="flash flash-info" role="status">
            <p><strong>ZIP-Update nicht möglich.</strong>
                <?php if (! $hasZipExtension) : ?>PHP-Erweiterung <code class="mono">zip</code> fehlt (ZipArchive).<?php endif; ?>
                <?php if (! $canShell) : ?> <code class="mono">proc_open</code> ist gesperrt.<?php endif; ?>
            </p>
        </div>
    <?php endif; ?>

    <?php if ($remote !== null && $updateAvailable && $canGitUpdate) : ?>
        <h3 class="update-section-title" style="font-size: 1rem; margin-top: 1.5rem;">Per Git</h3>
        <form method="post" action="<?= htmlspecialchars($app->url('/admin/update/apply'), ENT_QUOTES, 'UTF-8') ?>" class="admin-form wide update-apply-form" onsubmit="return confirm(<?= json_encode($gitConfirm, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?>);">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="channel" value="git">
            <input type="hidden" name="target_tag" value="<?= htmlspecialchars($remote['tag'], ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="git_mode" value="<?= htmlspecialchars($remote['git_mode'], ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="git_ref" value="<?= htmlspecialchars($remote['git_ref'], ENT_QUOTES, 'UTF-8') ?>">
            <label class="field field-inline">
                <input type="checkbox" name="confirm" value="1" required>
                <span>Ich bestätige: Backup liegt vor. Ich möchte per <strong>Git</strong> auf <strong><?= htmlspecialchars($remote['name'], ENT_QUOTES, 'UTF-8') ?></strong> aktualisieren.</span>
            </label>
            <button type="submit" class="button-secondary">Jetzt per Git &amp; Composer aktualisieren</button>
        </form>
    <?php endif; ?>

    <?php if ($remote !== null && $updateAvailable && ! $webUpdateAllowed) : ?>
        <p class="muted small">Nach dem Aktivieren von <code class="mono">BSPHOTO_ALLOW_WEB_UPDATE=1</code> (oder <code class="mono">BSPHOTO_ALLOW_GIT_UPDATE=1</code>) erscheint hier der Update-Button.</p>
    <?php endif; ?>
</section>
