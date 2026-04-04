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
    <p class="muted small">
        Auf vielen Webhosting-Paketen (z.&nbsp;B. reines FTP) ist kein Git installiert oder <code>proc_open</code> ist gesperrt — dann funktioniert der Button unten nicht.
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
        <p class="muted">Automatisches Update ist aktuell <strong>nicht möglich</strong>. Bitte ZIP-Link oben nutzen oder Server-Konfiguration anpassen.</p>
    <?php endif; ?>
</section>
