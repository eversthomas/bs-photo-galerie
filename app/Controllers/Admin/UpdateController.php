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
        $remote = $client->fetchLatestCached();

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
                'updateAvailable' => $newer,
                'repoUrl' => self::REPO_URL,
                'gitAllowed' => GitApplicationUpdater::isWebGitUpdateAllowed(),
                'hasGitDir' => $updater->isGitWorkingCopy(),
                'canShell' => $updater->canRunShell(),
            ],
            'admin/layout'
        );
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
        if ($targetTag === '') {
            Flash::set('error', 'Kein Ziel-Release übermittelt.');
            $this->app->redirect('/admin/update');

            return;
        }

        @unlink(rtrim($root, '/') . '/storage/cache/github_latest.json');
        $client = new GithubReleaseClient($root);
        $remote = $client->fetchLatestCached();
        if ($remote === null || $remote['tag'] !== $targetTag) {
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
        $result = $updater->run($targetTag);
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
