<?php

declare(strict_types=1);

namespace BSPhotoGalerie\Core;

use BSPhotoGalerie\Config\ConfigRepository;
use BSPhotoGalerie\Config\ImportSettings;
use BSPhotoGalerie\Config\MediaSettings;
use BSPhotoGalerie\Controllers\Admin\CategoryController;
use BSPhotoGalerie\Controllers\Admin\DashboardController;
use BSPhotoGalerie\Controllers\Admin\LoginController;
use BSPhotoGalerie\Controllers\Admin\ImportController;
use BSPhotoGalerie\Controllers\Admin\MediaController;
use BSPhotoGalerie\Controllers\Admin\SettingsController;
use BSPhotoGalerie\Controllers\GalleryController;
use BSPhotoGalerie\Controllers\HomeController;
use BSPhotoGalerie\Controllers\ThumbController;
use BSPhotoGalerie\Middleware\AuthMiddleware;
use BSPhotoGalerie\Middleware\CsrfMiddleware;
use BSPhotoGalerie\Models\CategoryRepository;
use BSPhotoGalerie\Models\MediaRepository;
use BSPhotoGalerie\Models\SettingsRepository;
use BSPhotoGalerie\Models\UserRepository;
use BSPhotoGalerie\Services\AuthService;
use BSPhotoGalerie\Services\Database;
use BSPhotoGalerie\Services\Import\MediaImportService;
use BSPhotoGalerie\Services\Media\MediaAssetService;
use BSPhotoGalerie\Services\Media\MediaUploadService;
use Intervention\Image\ImageManager;
use FastRoute\Dispatcher;
use FastRoute\RouteCollector;
use RuntimeException;
use Throwable;

use function FastRoute\simpleDispatcher;

/**
 * Front-Controller / DI-Light: hält Projektroot, Konfiguration, Request, Dienste.
 */
final class Application
{
    private array $config;

    private Request $request;

    private ?Database $database = null;

    private ?UserRepository $userRepository = null;

    private ?AuthService $auth = null;

    private ?MediaRepository $mediaRepository = null;

    private ?CategoryRepository $categoryRepository = null;

    private ?SettingsRepository $settingsRepository = null;

    private ?MediaUploadService $mediaUploadService = null;

    private ?MediaImportService $mediaImportService = null;

    private ?MediaAssetService $mediaAssetService = null;

    private ?Dispatcher $dispatcher = null;

    public function __construct(
        private string $projectRoot
    ) {
        $repo = new ConfigRepository();
        $this->config = $repo->load($this->projectRoot);
        $this->request = Request::fromGlobals();
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
        return $this->config;
    }

    public function request(): Request
    {
        return $this->request;
    }

    public function database(): Database
    {
        if ($this->database === null) {
            /** @var array{host:string,port?:int,name:string,user:string,password:string,charset?:string} $db */
            $db = $this->config['db'];
            $this->database = Database::connect($db);
        }

        return $this->database;
    }

    public function users(): UserRepository
    {
        if ($this->userRepository === null) {
            $this->userRepository = new UserRepository($this->database());
        }

        return $this->userRepository;
    }

    public function auth(): AuthService
    {
        if ($this->auth === null) {
            $this->auth = new AuthService($this->users());
        }

        return $this->auth;
    }

    public function mediaRepository(): MediaRepository
    {
        if ($this->mediaRepository === null) {
            $this->mediaRepository = new MediaRepository($this->database());
        }

        return $this->mediaRepository;
    }

    public function categoryRepository(): CategoryRepository
    {
        if ($this->categoryRepository === null) {
            $this->categoryRepository = new CategoryRepository($this->database());
        }

        return $this->categoryRepository;
    }

    public function settingsRepository(): SettingsRepository
    {
        if ($this->settingsRepository === null) {
            $this->settingsRepository = new SettingsRepository($this->database());
        }

        return $this->settingsRepository;
    }

    public function mediaUploadService(): MediaUploadService
    {
        if ($this->mediaUploadService === null) {
            $settings = MediaSettings::fromAppConfig($this->config);
            $this->mediaUploadService = new MediaUploadService(
                $this->projectRoot,
                $this->mediaRepository(),
                $settings,
                ImageManager::gd()
            );
        }

        return $this->mediaUploadService;
    }

    public function importSettings(): ImportSettings
    {
        return new ImportSettings($this->config);
    }

    public function mediaImportService(): MediaImportService
    {
        if ($this->mediaImportService === null) {
            $this->mediaImportService = new MediaImportService(
                $this->projectRoot,
                $this->mediaRepository(),
                $this->mediaUploadService(),
                $this->importSettings()
            );
        }

        return $this->mediaImportService;
    }

    public function mediaAssetService(): MediaAssetService
    {
        if ($this->mediaAssetService === null) {
            $this->mediaAssetService = new MediaAssetService(
                $this->projectRoot,
                $this->mediaRepository()
            );
        }

        return $this->mediaAssetService;
    }

    /**
     * Öffentliche URL inkl. Basis-Pfad unterhalb von public/.
     */
    public function url(string $path): string
    {
        $script = $this->request->server('SCRIPT_NAME') ?? '/index.php';
        $script = str_replace('\\', '/', (string) $script);
        $base = rtrim(dirname($script), '/');

        if ($base === '' || $base === '.') {
            $prefix = '';
        } else {
            $prefix = $base;
        }

        $path = '/' . ltrim($path, '/');
        if ($path === '//') {
            $path = '/';
        }

        return $prefix . $path;
    }

    public function redirect(string $path, int $status = 302): void
    {
        header('Location: ' . $this->url($path), true, $status);
        exit;
    }

    public function run(): void
    {
        SecurityHeaders::sendForApp();
        $this->ensureSession();
        $dispatcher = $this->router();
        $routeInfo = $dispatcher->dispatch($this->request->method(), $this->request->path());

        if ($routeInfo[0] === Dispatcher::NOT_FOUND) {
            $this->abort(404, 'Seite nicht gefunden.');

            return;
        }

        if ($routeInfo[0] === Dispatcher::METHOD_NOT_ALLOWED) {
            $this->abort(405, 'Methode nicht erlaubt.');

            return;
        }

        /** @var array{0:int,1:array{0:class-string,1:string},2:array<string,string>} $routeInfo */
        $handler = $routeInfo[1];
        $vars = $routeInfo[2];

        if ($this->request->isPost()) {
            (new CsrfMiddleware($this))->validatePost();
        }

        $this->applyAuthPolicy($handler);

        $this->invoke($handler, $vars);
    }

    /**
     * @param array{0:class-string,1:string} $handler
     * @param array<string, string> $vars
     */
    private function invoke(array $handler, array $vars): void
    {
        [$class, $method] = $handler;
        $controller = new $class($this);
        if (! method_exists($controller, $method)) {
            throw new RuntimeException('Ungültige Route: Methode fehlt.');
        }

        $controller->{$method}(...array_values($vars));
    }

    /**
     * @param array{0:class-string,1:string} $handler
     */
    private function applyAuthPolicy(array $handler): void
    {
        [$class] = $handler;

        if ($class === LoginController::class) {
            if ($this->auth()->check() && ($this->request->path() === '/admin/login')) {
                $this->redirect('/admin');
            }

            return;
        }

        if (str_starts_with($class, 'BSPhotoGalerie\\Controllers\\Admin\\')) {
            (new AuthMiddleware($this))->requireUser();
        }
    }

    public function abort(int $code, string $message): void
    {
        http_response_code($code);
        header('Content-Type: text/html; charset=utf-8');

        if ($code === 404) {
            $file = $this->projectRoot . '/templates/errors/404.php';
            if (is_file($file)) {
                require $file;
                exit;
            }
        }

        echo '<!DOCTYPE html><html lang="de"><head><meta charset="utf-8"><title>Fehler</title></head><body>';
        echo '<p>' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</p>';
        echo '</body></html>';
        exit;
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

        $this->dispatcher = simpleDispatcher(function (RouteCollector $r): void {
            $r->addRoute('GET', '/', [HomeController::class, 'index']);
            $r->addRoute('GET', '/galerie', [GalleryController::class, 'index']);
            $r->addRoute('GET', '/galerie/kategorie/{slug:[a-zA-Z0-9\\-]+}', [GalleryController::class, 'category']);
            $r->addRoute('GET', '/thumb/{id:\d+}', [ThumbController::class, 'show']);
            $r->addRoute('GET', '/admin', [DashboardController::class, 'index']);
            $r->addGroup('/admin', function (RouteCollector $r): void {
                $r->addRoute('GET', '/login', [LoginController::class, 'showForm']);
                $r->addRoute('POST', '/login', [LoginController::class, 'login']);
                $r->addRoute('POST', '/logout', [DashboardController::class, 'logout']);
                $r->addRoute('GET', '/categories', [CategoryController::class, 'index']);
                $r->addRoute('GET', '/categories/create', [CategoryController::class, 'create']);
                $r->addRoute('POST', '/categories/store', [CategoryController::class, 'store']);
                $r->addRoute('GET', '/categories/{id:\d+}/edit', [CategoryController::class, 'edit']);
                $r->addRoute('POST', '/categories/{id:\d+}/update', [CategoryController::class, 'update']);
                $r->addRoute('POST', '/categories/{id:\d+}/delete', [CategoryController::class, 'delete']);
                $r->addRoute('GET', '/media', [MediaController::class, 'index']);
                $r->addRoute('GET', '/media/upload', [MediaController::class, 'uploadForm']);
                $r->addRoute('POST', '/media/upload', [MediaController::class, 'upload']);
                $r->addRoute('POST', '/media/reorder', [MediaController::class, 'reorder']);
                $r->addRoute('POST', '/media/bulk-category', [MediaController::class, 'bulkCategory']);
                $r->addRoute('POST', '/media/inline-title', [MediaController::class, 'inlineTitle']);
                $r->addRoute('GET', '/media/{id:\d+}/edit', [MediaController::class, 'edit']);
                $r->addRoute('POST', '/media/{id:\d+}/update', [MediaController::class, 'update']);
                $r->addRoute('POST', '/media/{id:\d+}/delete', [MediaController::class, 'destroy']);
                $r->addRoute('GET', '/import', [ImportController::class, 'index']);
                $r->addRoute('POST', '/import/run', [ImportController::class, 'run']);
                $r->addRoute('GET', '/settings', [SettingsController::class, 'index']);
                $r->addRoute('POST', '/settings/update', [SettingsController::class, 'update']);
            });
        });

        return $this->dispatcher;
    }

    /**
     * Globale Fehlerbehandlung für den Front-Controller (optional).
     */
    public static function handleException(Throwable $e): void
    {
        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');
        echo '<p>Interner Fehler.</p>';
    }
}
