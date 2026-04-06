<?php

declare(strict_types=1);

namespace BSPhotoGalerie\Core;

use BSPhotoGalerie\Config\ConfigRepository;
use BSPhotoGalerie\Config\ImportSettings;
use BSPhotoGalerie\Controllers\Admin\CategoryController;
use BSPhotoGalerie\Controllers\Admin\DashboardController;
use BSPhotoGalerie\Controllers\Admin\LoginController;
use BSPhotoGalerie\Controllers\Admin\ImportController;
use BSPhotoGalerie\Controllers\Admin\MediaController;
use BSPhotoGalerie\Controllers\Admin\SettingsController;
use BSPhotoGalerie\Controllers\Admin\UpdateController;
use BSPhotoGalerie\Controllers\GalleryController;
use BSPhotoGalerie\Controllers\HomeController;
use BSPhotoGalerie\Controllers\ThumbController;
use BSPhotoGalerie\Middleware\AuthMiddleware;
use BSPhotoGalerie\Middleware\CsrfMiddleware;
use BSPhotoGalerie\Models\CategoryRepository;
use BSPhotoGalerie\Models\MediaRepository;
use BSPhotoGalerie\Models\SettingsRepository;
use BSPhotoGalerie\Models\UserRepository;
use BSPhotoGalerie\Services\Application\ImportRunService;
use BSPhotoGalerie\Services\Application\MediaItemApplicationService;
use BSPhotoGalerie\Services\Application\SettingsUpdateService;
use BSPhotoGalerie\Services\Application\UpdateApplyService;
use BSPhotoGalerie\Services\AuthService;
use BSPhotoGalerie\Services\Database;
use BSPhotoGalerie\Services\Domain\CategoryAdminService;
use BSPhotoGalerie\Services\Domain\MediaAdminService;
use BSPhotoGalerie\Services\Import\MediaImportService;
use BSPhotoGalerie\Services\Media\MediaAssetService;
use BSPhotoGalerie\Services\Media\MediaUploadService;
use FastRoute\Dispatcher;
use FastRoute\RouteCollector;
use RuntimeException;
use Throwable;

use function FastRoute\simpleDispatcher;

/**
 * Front-Controller: Request, Routing, Session; Dienste über {@see Container}.
 */
final class Application
{
    private Container $container;

    private Request $request;

    private ?Dispatcher $dispatcher = null;

    public function __construct(
        private string $projectRoot
    ) {
        $repo = new ConfigRepository();
        $config = $repo->load($this->projectRoot);
        $this->container = new Container($this->projectRoot, $config);
        $this->request = Request::fromGlobals();
    }

    public function container(): Container
    {
        return $this->container;
    }

    public function root(): string
    {
        return $this->projectRoot;
    }

    /**
     * @return array<string, mixed>
     */
    public function config(): array
    {
        return $this->container->config();
    }

    public function request(): Request
    {
        return $this->request;
    }

    public function database(): Database
    {
        return $this->container->database();
    }

    public function users(): UserRepository
    {
        return $this->container->users();
    }

    public function auth(): AuthService
    {
        return $this->container->auth();
    }

    public function mediaRepository(): MediaRepository
    {
        return $this->container->mediaRepository();
    }

    public function categoryRepository(): CategoryRepository
    {
        return $this->container->categoryRepository();
    }

    public function settingsRepository(): SettingsRepository
    {
        return $this->container->settingsRepository();
    }

    public function mediaUploadService(): MediaUploadService
    {
        return $this->container->mediaUploadService();
    }

    public function importSettings(): ImportSettings
    {
        return $this->container->importSettings();
    }

    public function mediaImportService(): MediaImportService
    {
        return $this->container->mediaImportService();
    }

    public function mediaAssetService(): MediaAssetService
    {
        return $this->container->mediaAssetService();
    }

    public function categoryAdminService(): CategoryAdminService
    {
        return $this->container->categoryAdminService();
    }

    public function mediaAdminService(): MediaAdminService
    {
        return $this->container->mediaAdminService();
    }

    public function updateApplyService(): UpdateApplyService
    {
        return $this->container->updateApplyService();
    }

    public function settingsUpdateService(): SettingsUpdateService
    {
        return $this->container->settingsUpdateService();
    }

    public function importRunService(): ImportRunService
    {
        return $this->container->importRunService();
    }

    public function mediaItemApplicationService(): MediaItemApplicationService
    {
        return $this->container->mediaItemApplicationService();
    }

    public function run(): void
    {
        SecurityHeaders::sendForApp();
        $this->ensureSession();
        $http = new HttpContext(
            $this->request,
            $this->projectRoot,
            $this->container->settingsRepository()
        );
        $dispatcher = $this->router();
        $routeInfo = $dispatcher->dispatch($this->request->method(), $this->request->path());

        if ($routeInfo[0] === Dispatcher::NOT_FOUND) {
            $http->abort(404, 'Seite nicht gefunden.');

            return;
        }

        if ($routeInfo[0] === Dispatcher::METHOD_NOT_ALLOWED) {
            $http->abort(405, 'Methode nicht erlaubt.');

            return;
        }

        /** @var array{0:int,1:array{handler: array{0:class-string,1:string}, auth: string},2:array<string,string>} $routeInfo */
        $route = $routeInfo[1];
        $handler = $route['handler'];
        $vars = $routeInfo[2];

        if ($this->request->isPost()) {
            (new CsrfMiddleware($http))->validatePost();
        }

        $this->applyAuthPolicy($route, $http);

        $this->invoke($handler, $vars, $http);
    }

    /**
     * @param array{0:class-string,1:string} $handler
     * @param array<string, string> $vars
     */
    private function invoke(array $handler, array $vars, HttpContext $http): void
    {
        [$class, $method] = $handler;
        $controller = $this->container->make($http, $class);
        if (! method_exists($controller, $method)) {
            throw new RuntimeException('Ungültige Route: Methode fehlt.');
        }

        $controller->{$method}(...array_values($vars));
    }

    /**
     * @param array{handler: array{0:class-string,1:string}, auth: string} $route
     */
    private function applyAuthPolicy(array $route, HttpContext $http): void
    {
        $auth = $route['auth'];
        [$class] = $route['handler'];

        if ($auth === 'login') {
            if ($class === LoginController::class && $this->auth()->check() && $this->request->path() === '/admin/login') {
                $http->redirect('/admin');
            }

            return;
        }

        if ($auth === 'admin') {
            (new AuthMiddleware($this->container->auth(), $http))->requireUser();
        }
    }

    private function ensureSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $secure = $this->request->server('HTTPS') === 'on'
            || $this->request->server('HTTP_X_FORWARDED_PROTO') === 'https';

        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        session_name('BSPHOTO_SESSION');
        session_start();
    }

    private function router(): Dispatcher
    {
        if ($this->dispatcher !== null) {
            return $this->dispatcher;
        }

        $R = static fn (string $auth, array $handler): array => ['handler' => $handler, 'auth' => $auth];

        $this->dispatcher = simpleDispatcher(function (RouteCollector $r) use ($R): void {
            $r->addRoute('GET', '/', $R('public', [HomeController::class, 'index']));
            $r->addRoute('GET', '/galerie', $R('public', [GalleryController::class, 'index']));
            $r->addRoute('GET', '/galerie/kategorie/{slug:[a-zA-Z0-9\\-]+}', $R('public', [GalleryController::class, 'category']));
            $r->addRoute('GET', '/thumb/{id:\d+}', $R('public', [ThumbController::class, 'show']));
            $r->addRoute('GET', '/admin', $R('admin', [DashboardController::class, 'index']));
            $r->addGroup('/admin', function (RouteCollector $r) use ($R): void {
                $r->addRoute('GET', '/login', $R('login', [LoginController::class, 'showForm']));
                $r->addRoute('POST', '/login', $R('login', [LoginController::class, 'login']));
                $r->addRoute('POST', '/logout', $R('admin', [DashboardController::class, 'logout']));
                $r->addRoute('GET', '/categories', $R('admin', [CategoryController::class, 'index']));
                $r->addRoute('GET', '/categories/create', $R('admin', [CategoryController::class, 'create']));
                $r->addRoute('POST', '/categories/store', $R('admin', [CategoryController::class, 'store']));
                $r->addRoute('GET', '/categories/{id:\d+}/edit', $R('admin', [CategoryController::class, 'edit']));
                $r->addRoute('POST', '/categories/{id:\d+}/update', $R('admin', [CategoryController::class, 'update']));
                $r->addRoute('POST', '/categories/{id:\d+}/delete', $R('admin', [CategoryController::class, 'delete']));
                $r->addRoute('GET', '/media', $R('admin', [MediaController::class, 'index']));
                $r->addRoute('GET', '/media/upload', $R('admin', [MediaController::class, 'uploadForm']));
                $r->addRoute('POST', '/media/upload', $R('admin', [MediaController::class, 'upload']));
                $r->addRoute('POST', '/media/reorder', $R('admin', [MediaController::class, 'reorder']));
                $r->addRoute('POST', '/media/bulk-category', $R('admin', [MediaController::class, 'bulkCategory']));
                $r->addRoute('POST', '/media/inline-title', $R('admin', [MediaController::class, 'inlineTitle']));
                $r->addRoute('GET', '/media/{id:\d+}/edit', $R('admin', [MediaController::class, 'edit']));
                $r->addRoute('POST', '/media/{id:\d+}/update', $R('admin', [MediaController::class, 'update']));
                $r->addRoute('POST', '/media/{id:\d+}/delete', $R('admin', [MediaController::class, 'destroy']));
                $r->addRoute('GET', '/import', $R('admin', [ImportController::class, 'index']));
                $r->addRoute('POST', '/import/run', $R('admin', [ImportController::class, 'run']));
                $r->addRoute('GET', '/settings', $R('admin', [SettingsController::class, 'index']));
                $r->addRoute('POST', '/settings/update', $R('admin', [SettingsController::class, 'update']));
                $r->addRoute('GET', '/update', $R('admin', [UpdateController::class, 'index']));
                $r->addRoute('POST', '/update/refresh', $R('admin', [UpdateController::class, 'refreshCache']));
                $r->addRoute('POST', '/update/apply', $R('admin', [UpdateController::class, 'apply']));
            });
        });

        return $this->dispatcher;
    }

    /**
     * Globale Fehlerbehandlung für den Front-Controller.
     *
     * @param string|null $projectRoot Projektroot für Logdatei unter storage/logs/; null nur file-less error_log.
     * @param string|null $requestSummary z. B. "GET /galerie" für Zuordnung.
     */
    public static function handleException(Throwable $e, ?string $projectRoot = null, ?string $requestSummary = null): void
    {
        if ($projectRoot !== null && $projectRoot !== '') {
            ExceptionLogger::logThrowable($projectRoot, $e, $requestSummary);
        } else {
            error_log(
                '[BSPHOTO] '
                . $e::class
                . ': '
                . $e->getMessage()
                . ' in '
                . $e->getFile()
                . ':'
                . (string) $e->getLine()
            );
        }

        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');

        if (self::exceptionDebugEnabled()) {
            echo '<!DOCTYPE html><html lang="de"><head><meta charset="utf-8"><title>Fehler (Debug)</title></head><body>';
            echo '<h1>Interner Fehler</h1>';
            echo '<pre>'
                . htmlspecialchars(
                    $e::class . ': ' . $e->getMessage() . "\n\n" . $e->getTraceAsString(),
                    ENT_QUOTES,
                    'UTF-8'
                )
                . '</pre></body></html>';

            return;
        }

        echo '<p>Interner Fehler.</p>';
    }

    private static function exceptionDebugEnabled(): bool
    {
        $raw = $_ENV['APP_DEBUG'] ?? getenv('APP_DEBUG');
        if ($raw === false || $raw === null || $raw === '') {
            return false;
        }

        return in_array(strtolower(trim((string) $raw)), ['1', 'true', 'yes', 'on'], true);
    }
}
