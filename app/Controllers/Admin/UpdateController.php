<?php

declare(strict_types=1);

namespace BSPhotoGalerie\Controllers\Admin;

use BSPhotoGalerie\Controllers\BaseController;
use BSPhotoGalerie\Core\Flash;
use BSPhotoGalerie\Core\HttpContext;
use BSPhotoGalerie\Services\AuthService;
use BSPhotoGalerie\Services\Update\AppVersion;
use BSPhotoGalerie\Services\Update\GithubReleaseClient;
use BSPhotoGalerie\Services\Update\GitApplicationUpdater;
use BSPhotoGalerie\Services\Update\WebUpdatePolicy;
use BSPhotoGalerie\Services\Update\ZipReleaseUpdater;

/**
 * GitHub-Versionscheck: Update per Git (mit .git) oder per GitHub-ZIP (ohne .git).
 */
final class UpdateController extends BaseController
{
    private const REPO_URL = 'https://github.com/eversthomas/bs-photo-galerie';

    public function __construct(
        HttpContext $http,
        private AuthService $auth
    ) {
        parent::__construct($http);
    }

    public function index(): void
    {
        $root = $this->http->root();
        $local = AppVersion::readFromProjectRoot($root);

        $client = new GithubReleaseClient($root);
        $check = $client->fetchLatestCached();
        $remote = $check['remote'];

        $newer = false;
        if ($remote !== null) {
            $newer = AppVersion::isNewerThan($remote['tag'], $local);
        }

        $git = new GitApplicationUpdater($root);
        $zip = new ZipReleaseUpdater($root);
        $webOk = WebUpdatePolicy::isWebUpdateAllowed();
        $this->render(
            'admin/update/index',
            [
                'title' => 'Software-Update',
                'user' => $this->auth->user(),
                'flash' => Flash::pull(),
                'localVersion' => $local,
                'remote' => $remote,
                'remoteError' => $check['error'],
                'remoteDiagnostic' => $check['diagnostic'],
                'updateAvailable' => $newer,
                'repoUrl' => self::REPO_URL,
                'webUpdateAllowed' => $webOk,
                'hasZipExtension' => ZipReleaseUpdater::hasZipExtension(),
                'gitAllowed' => $webOk,
                'hasGitDir' => $git->isGitWorkingCopy(),
                'canShell' => $git->canRunShell(),
                'canZipUpdate' => $webOk && ZipReleaseUpdater::hasZipExtension(),
            ],
            'admin/layout'
        );
    }

    public function refreshCache(): void
    {
        $root = $this->http->root();
        (new GithubReleaseClient($root))->clearCache();
        Flash::set('success', 'Update-Cache wurde geleert. Der GitHub-Stand wird beim nächsten Aufruf neu geladen.');
        $this->http->redirect('/admin/update');
    }

    public function apply(): void
    {
        $root = $this->http->root();
        $local = AppVersion::readFromProjectRoot($root);

        if ($this->http->request()->post('confirm') !== '1') {
            Flash::set('error', 'Bitte die Bestätigung aktivieren.');
            $this->http->redirect('/admin/update');

            return;
        }

        if (! WebUpdatePolicy::isWebUpdateAllowed()) {
            Flash::set('error', 'Web-Updates sind nicht freigeschaltet. In config/.env z. B. BSPHOTO_ALLOW_WEB_UPDATE=1 setzen.');
            $this->http->redirect('/admin/update');

            return;
        }

        $channel = strtolower(trim((string) $this->http->request()->post('channel', '')));
        if ($channel !== 'git' && $channel !== 'zip') {
            Flash::set('error', 'Ungültiger Update-Typ.');
            $this->http->redirect('/admin/update');

            return;
        }

        $git = new GitApplicationUpdater($root);
        if ($channel === 'git' && ! $git->isGitWorkingCopy()) {
            Flash::set('error', 'Git-Update erfordert ein Arbeitsverzeichnis mit .git. Nutzen Sie stattdessen das ZIP-Update.');
            $this->http->redirect('/admin/update');

            return;
        }

        $targetTag = trim((string) $this->http->request()->post('target_tag', ''));
        $postedMode = strtolower(trim((string) $this->http->request()->post('git_mode', 'tag')));
        $postedRef = trim((string) $this->http->request()->post('git_ref', ''));
        if ($postedMode !== 'tag' && $postedMode !== 'branch') {
            $postedMode = 'tag';
        }
        if ($postedRef === '' && $postedMode === 'tag') {
            $postedRef = $targetTag;
        }

        if ($targetTag === '') {
            Flash::set('error', 'Kein Ziel-Release übermittelt.');
            $this->http->redirect('/admin/update');

            return;
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
            Flash::set('error', 'Release-Informationen konnten nicht verifiziert werden. Bitte Seite neu laden und erneut versuchen.');
            $this->http->redirect('/admin/update');

            return;
        }

        if (! AppVersion::isNewerThan($remote['tag'], $local)) {
            Flash::set('error', 'Es liegt bereits eine gleiche oder neuere Version vor.');
            $this->http->redirect('/admin/update');

            return;
        }

        if ($channel === 'zip') {
            if ($remote['zipball_url'] === '') {
                Flash::set('error', 'Keine ZIP-URL von GitHub — Update derzeit nicht möglich.');
                $this->http->redirect('/admin/update');

                return;
            }
            if (! ZipReleaseUpdater::hasZipExtension()) {
                Flash::set('error', 'ZIP-Update nicht möglich: PHP-Erweiterung zip (ZipArchive) fehlt.');
                $this->http->redirect('/admin/update');

                return;
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
            Flash::set('success', $msg);
        } else {
            Flash::set('error', 'Update fehlgeschlagen: ' . implode('; ', array_slice($result['log'], 0, 12)));
        }

        $this->http->redirect('/admin/update');
    }
}
