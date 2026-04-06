<?php

declare(strict_types=1);

namespace BSPhotoGalerie\Services\Application;

use BSPhotoGalerie\Core\AuditLog;
use BSPhotoGalerie\Core\Request;
use BSPhotoGalerie\Events\AfterGitUpdateAppliedEvent;
use BSPhotoGalerie\Events\AfterZipUpdateEvent;
use BSPhotoGalerie\Events\EventDispatcher;
use BSPhotoGalerie\Services\Update\AppVersion;
use BSPhotoGalerie\Services\Update\GithubReleaseClient;
use BSPhotoGalerie\Services\Update\GitApplicationUpdater;
use BSPhotoGalerie\Services\Update\WebUpdatePolicy;
use BSPhotoGalerie\Services\Update\ZipReleaseUpdater;

/**
 * Anwendungsfall: Software-Update aus dem Admin (GitHub ZIP oder Git) inkl. Validierung.
 */
final class UpdateApplyService
{
    public function __construct(
        private string $projectRoot,
        private EventDispatcher $events,
        private AuditLog $audit
    ) {
    }

    public function clearGithubReleaseCache(): void
    {
        (new GithubReleaseClient($this->projectRoot))->clearCache();
    }

    public function applyFromRequest(Request $request): UpdateApplyResult
    {
        $root = $this->projectRoot;
        $local = AppVersion::readFromProjectRoot($root);

        if ($request->post('confirm') !== '1') {
            return new UpdateApplyResult('error', 'Bitte die Bestätigung aktivieren.');
        }

        if (! WebUpdatePolicy::isWebUpdateAllowed()) {
            return new UpdateApplyResult(
                'error',
                'Web-Updates sind nicht freigeschaltet. In config/.env z. B. BSPHOTO_ALLOW_WEB_UPDATE=1 setzen.'
            );
        }

        $channel = strtolower(trim((string) $request->post('channel', '')));
        if ($channel !== 'git' && $channel !== 'zip') {
            return new UpdateApplyResult('error', 'Ungültiger Update-Typ.');
        }

        $git = new GitApplicationUpdater($root);
        if ($channel === 'git' && ! $git->isGitWorkingCopy()) {
            return new UpdateApplyResult(
                'error',
                'Git-Update erfordert ein Arbeitsverzeichnis mit .git. Nutzen Sie stattdessen das ZIP-Update.'
            );
        }

        $targetTag = trim((string) $request->post('target_tag', ''));
        $postedMode = strtolower(trim((string) $request->post('git_mode', 'tag')));
        $postedRef = trim((string) $request->post('git_ref', ''));
        if ($postedMode !== 'tag' && $postedMode !== 'branch') {
            $postedMode = 'tag';
        }
        if ($postedRef === '' && $postedMode === 'tag') {
            $postedRef = $targetTag;
        }

        if ($targetTag === '') {
            return new UpdateApplyResult('error', 'Kein Ziel-Release übermittelt.');
        }

        (new GithubReleaseClient($root))->clearCache();
        $check = (new GithubReleaseClient($root))->fetchLatestCached();
        $remote = $check['remote'];
        if (
            $remote === null
            || AppVersion::normalize($remote['tag']) !== AppVersion::normalize($targetTag)
            || $remote['git_mode'] !== $postedMode
            || $remote['git_ref'] !== $postedRef
        ) {
            return new UpdateApplyResult(
                'error',
                'Release-Informationen konnten nicht verifiziert werden. Bitte Seite neu laden und erneut versuchen.'
            );
        }

        if (! AppVersion::isNewerThan($remote['tag'], $local)) {
            return new UpdateApplyResult('error', 'Es liegt bereits eine gleiche oder neuere Version vor.');
        }

        $this->audit->record('update.apply.executing', [
            'channel' => $channel,
            'target_tag' => $targetTag,
            'from_version' => $local,
            'release_tag' => $remote['tag'],
        ]);

        if ($channel === 'zip') {
            if ($remote['zipball_url'] === '') {
                return new UpdateApplyResult('error', 'Keine ZIP-URL von GitHub — Update derzeit nicht möglich.');
            }
            if (! ZipReleaseUpdater::hasZipExtension()) {
                return new UpdateApplyResult(
                    'error',
                    'ZIP-Update nicht möglich: PHP-Erweiterung zip (ZipArchive) fehlt.'
                );
            }
            $result = (new ZipReleaseUpdater($root))->run($remote['zipball_url']);
        } else {
            $result = $git->run($remote['git_ref'], $remote['git_mode']);
        }

        if ($result['ok']) {
            $newLocal = AppVersion::readFromProjectRoot($root);
            $msg = 'Update ausgeführt. Datei VERSION: ' . $newLocal . '.';
            if ($channel === 'zip') {
                $msg .= ' Ohne Composer auf dem Server bleibt der Ordner vendor/ unverändert (normal für viele Releases). Wenn composer.json/composer.lock neue Pakete verlangen, lokal composer install ausführen und vendor/ hochladen.';
            } elseif (isset($result['composer_ok']) && $result['composer_ok'] === false) {
                $msg .= ' Der Git-Stand wurde aktualisiert; composer install ist fehlgeschlagen oder war nicht verfügbar — bitte vendor/ ggf. lokal erzeugen und hochladen.';
            } else {
                $msg .= ' Bei Problemen prüfen Sie die Server-Logs.';
            }

            if ($channel === 'zip') {
                $this->events->dispatch(
                    new AfterZipUpdateEvent($root, $local, $newLocal, $remote['tag'])
                );
            } else {
                $this->events->dispatch(
                    new AfterGitUpdateAppliedEvent($root, $local, $newLocal, $remote['tag'])
                );
            }

            $this->audit->record('update.apply.success', [
                'channel' => $channel,
                'from_version' => $local,
                'to_version' => $newLocal,
                'release_tag' => $remote['tag'],
                'composer_ok' => $result['composer_ok'] ?? null,
            ]);

            return new UpdateApplyResult('success', $msg);
        }

        $this->audit->record('update.apply.failed', [
            'channel' => $channel,
            'from_version' => $local,
            'release_tag' => $remote['tag'],
            'detail' => implode('; ', array_slice($result['log'], 0, 8)),
        ]);

        return new UpdateApplyResult(
            'error',
            'Update fehlgeschlagen: ' . implode('; ', array_slice($result['log'], 0, 12))
        );
    }
}
