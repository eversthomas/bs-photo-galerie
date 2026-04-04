<?php

declare(strict_types=1);

namespace BSPhotoGalerie\Controllers\Admin;

use BSPhotoGalerie\Controllers\BaseController;
use BSPhotoGalerie\Core\Flash;
use BSPhotoGalerie\Services\Update\AppVersion;
use BSPhotoGalerie\Services\Update\GithubReleaseClient;
use BSPhotoGalerie\Services\Update\GitApplicationUpdater;

/**
 * GitHub-Versionscheck und optionales Git-Update (nur mit BSPHOTO_ALLOW_GIT_UPDATE=1).
 */
final class UpdateController extends BaseController
{
    private const REPO_URL = 'https://github.com/eversthomas/bs-photo-galerie';

    public function index(): void
    {
        $root = $this->app->root();
        $local = AppVersion::readFromProjectRoot($root);

        $client = new GithubReleaseClient($root);
        $check = $client->fetchLatestCached();
        $remote = $check['remote'];

        $newer = false;
        if ($remote !== null) {
            $newer = AppVersion::isNewerThan($remote['tag'], $local);
        }

        $updater = new GitApplicationUpdater($root);
        $this->render(
            'admin/update/index',
            [
                'title' => 'Software-Update',
                'user' => $this->app->auth()->user(),
                'flash' => Flash::pull() ?? [],
                'localVersion' => $local,
                'remote' => $remote,
                'remoteError' => $check['error'],
                'remoteDiagnostic' => $check['diagnostic'],
                'updateAvailable' => $newer,
                'repoUrl' => self::REPO_URL,
                'gitAllowed' => GitApplicationUpdater::isWebGitUpdateAllowed(),
                'hasGitDir' => $updater->isGitWorkingCopy(),
                'canShell' => $updater->canRunShell(),
            ],
            'admin/layout'
        );
    }

    public function refreshCache(): void
    {
        $root = $this->app->root();
        (new GithubReleaseClient($root))->clearCache();
        Flash::set('success', 'Update-Cache wurde geleert. Der GitHub-Stand wird beim nächsten Aufruf neu geladen.');
        $this->app->redirect('/admin/update');
    }

    public function apply(): void
    {
        $root = $this->app->root();
        $local = AppVersion::readFromProjectRoot($root);

        if ($this->app->request()->post('confirm') !== '1') {
            Flash::set('error', 'Bitte die Bestätigung aktivieren.');
            $this->app->redirect('/admin/update');

            return;
        }

        $targetTag = trim((string) $this->app->request()->post('target_tag', ''));
        $postedMode = strtolower(trim((string) $this->app->request()->post('git_mode', 'tag')));
        $postedRef = trim((string) $this->app->request()->post('git_ref', ''));
        if ($postedMode !== 'tag' && $postedMode !== 'branch') {
            $postedMode = 'tag';
        }
        if ($postedRef === '' && $postedMode === 'tag') {
            $postedRef = $targetTag;
        }

        if ($targetTag === '') {
            Flash::set('error', 'Kein Ziel-Release übermittelt.');
            $this->app->redirect('/admin/update');

            return;
        }

        (new GithubReleaseClient($root))->clearCache();
        $client = new GithubReleaseClient($root);
        $check = $client->fetchLatestCached();
        $remote = $check['remote'];
        if (
            $remote === null
            || AppVersion::normalize($remote['tag']) !== AppVersion::normalize($targetTag)
            || $remote['git_mode'] !== $postedMode
            || $remote['git_ref'] !== $postedRef
        ) {
            Flash::set('error', 'Release-Informationen konnten nicht verifiziert werden. Bitte Seite neu laden und erneut versuchen.');
            $this->app->redirect('/admin/update');

            return;
        }

        if (! AppVersion::isNewerThan($remote['tag'], $local)) {
            Flash::set('error', 'Es liegt bereits eine gleiche oder neuere Version vor.');
            $this->app->redirect('/admin/update');

            return;
        }

        $updater = new GitApplicationUpdater($root);
        $result = $updater->run($remote['git_ref'], $remote['git_mode']);
        if ($result['ok']) {
            $newLocal = AppVersion::readFromProjectRoot($root);
            Flash::set(
                'success',
                'Update ausgeführt. Datei VERSION zeigt nun: ' . $newLocal . '. Bei Problemen siehe untenstehendes Protokoll in den Server-Logs bzw. führen Sie composer install manuell aus.'
            );
        } else {
            Flash::set('error', 'Update fehlgeschlagen: ' . implode('; ', array_slice($result['log'], 0, 10)));
        }

        $this->app->redirect('/admin/update');
    }
}
