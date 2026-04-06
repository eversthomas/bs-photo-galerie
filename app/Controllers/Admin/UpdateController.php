<?php

declare(strict_types=1);

namespace BSPhotoGalerie\Controllers\Admin;

use BSPhotoGalerie\Controllers\BaseController;
use BSPhotoGalerie\Core\Flash;
use BSPhotoGalerie\Core\HttpContext;
use BSPhotoGalerie\Services\AuthService;
use BSPhotoGalerie\Services\Application\UpdateApplyService;
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
        private AuthService $auth,
        private UpdateApplyService $updateApply
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
        $this->updateApply->clearGithubReleaseCache();
        Flash::set('success', 'Update-Cache wurde geleert. Der GitHub-Stand wird beim nächsten Aufruf neu geladen.');
        $this->http->redirect('/admin/update');
    }

    public function apply(): void
    {
        $outcome = $this->updateApply->applyFromRequest($this->http->request());
        Flash::set($outcome->flashType, $outcome->flashMessage);
        $this->http->redirect('/admin/update');
    }
}
