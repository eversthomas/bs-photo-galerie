<?php

declare(strict_types=1);

namespace BSPhotoGalerie\Core;

use BSPhotoGalerie\Config\ImportSettings;
use BSPhotoGalerie\Config\MediaSettings;
use BSPhotoGalerie\Core\AuditLog;
use BSPhotoGalerie\Events\EventDispatcher;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionUnionType;
use RuntimeException;
use BSPhotoGalerie\Models\CategoryRepository;
use BSPhotoGalerie\Models\MediaRepository;
use BSPhotoGalerie\Models\SettingsRepository;
use BSPhotoGalerie\Models\UserRepository;
use BSPhotoGalerie\Services\AuthService;
use BSPhotoGalerie\Services\Database;
use BSPhotoGalerie\Services\Domain\CategoryAdminService;
use BSPhotoGalerie\Services\Domain\MediaAdminService;
use BSPhotoGalerie\Services\Application\ImportRunService;
use BSPhotoGalerie\Services\Application\MediaItemApplicationService;
use BSPhotoGalerie\Services\Application\SettingsUpdateService;
use BSPhotoGalerie\Services\Application\UpdateApplyService;
use BSPhotoGalerie\Services\Import\MediaImportService;
use BSPhotoGalerie\Services\Category\CategoryService;
use BSPhotoGalerie\Services\Media\MediaAssetService;
use BSPhotoGalerie\Services\Media\MediaUploadService;
use BSPhotoGalerie\Services\Media\UploadContentScannerInterface;
use BSPhotoGalerie\Services\Media\UploadScannerChain;
use BSPhotoGalerie\Services\Media\UploadSecurityPolicy;
use BSPhotoGalerie\Services\SchemaPatches;
use Intervention\Image\ImageManager;

/**
 * Service-Locator-Light: lazy Singletons für Datenbank, Repositories und Anwendungsdienste.
 */
final class Container
{
    private ?Database $database = null;

    private ?UserRepository $userRepository = null;

    private ?AuthService $auth = null;

    private ?MediaRepository $mediaRepository = null;

    private ?CategoryRepository $categoryRepository = null;

    private ?SettingsRepository $settingsRepository = null;

    private ?MediaUploadService $mediaUploadService = null;

    private ?MediaImportService $mediaImportService = null;

    private ?MediaAssetService $mediaAssetService = null;

    private ?CategoryAdminService $categoryAdminService = null;

    private ?MediaAdminService $mediaAdminService = null;

    private ?UpdateApplyService $updateApplyService = null;

    private ?SettingsUpdateService $settingsUpdateService = null;

    private ?ImportRunService $importRunService = null;

    private ?MediaItemApplicationService $mediaItemApplicationService = null;

    private ?EventDispatcher $eventDispatcher = null;

    /** @var array<string, callable(self): object> */
    private array $bindings = [];

    /** @var array<string, object> */
    private array $bindingInstances = [];

    /** @var list<UploadContentScannerInterface> */
    private array $uploadScanners = [];

    private ?UploadScannerChain $uploadScannerChainInstance = null;

    private ?CategoryService $categoryService = null;

    private ?AuditLog $auditLog = null;

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(
        private string $projectRoot,
        private array $config
    ) {
    }

    public function projectRoot(): string
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

    /**
     * Erweiterung: eigene Singletons/Factories registrieren (Vollqualifizierter Klassenname als $id).
     *
     * @param callable(self): object $factory
     */
    public function register(string $id, callable $factory): void
    {
        if (isset($this->bindingInstances[$id])) {
            throw new RuntimeException('Service "' . $id . '" wurde bereits instanziiert und kann nicht neu registriert werden.');
        }
        $this->bindings[$id] = $factory;
    }

    /**
     * @template T of object
     * @param class-string<T> $id
     * @return T
     */
    public function get(string $id): object
    {
        if (! isset($this->bindings[$id]) && ! isset($this->bindingInstances[$id])) {
            throw new RuntimeException('Unbekannte Service-ID: ' . $id);
        }

        /** @var T */
        return $this->resolveRegistered($id);
    }

    public function addUploadScanner(UploadContentScannerInterface $scanner): void
    {
        if ($this->uploadScannerChainInstance !== null) {
            throw new RuntimeException('Upload-Scanner können nicht registriert werden, die Kette wurde bereits erzeugt.');
        }
        if ($this->mediaUploadService !== null) {
            throw new RuntimeException('Upload-Scanner zu spät registriert (MediaUploadService bereits aktiv).');
        }

        $this->uploadScanners[] = $scanner;
    }

    public function database(): Database
    {
        if ($this->database === null) {
            /** @var array{host:string,port?:int,name:string,user:string,password:string,charset?:string} $db */
            $db = $this->config['db'];
            $this->database = Database::connect($db);
            SchemaPatches::ensure($this->database);
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
            $this->auth = new AuthService($this->users(), $this->auditLog());
        }

        return $this->auth;
    }

    public function auditLog(): AuditLog
    {
        if ($this->auditLog === null) {
            $this->auditLog = new AuditLog($this->projectRoot);
        }

        return $this->auditLog;
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
            $policy = new UploadSecurityPolicy($settings);
            $this->mediaUploadService = new MediaUploadService(
                $this->projectRoot,
                $this->mediaRepository(),
                $policy,
                ImageManager::gd(),
                $this->eventDispatcher(),
                $this->uploadScannerChain()
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

    public function categoryAdminService(): CategoryAdminService
    {
        if ($this->categoryAdminService === null) {
            $this->categoryAdminService = new CategoryAdminService($this->categoryRepository());
        }

        return $this->categoryAdminService;
    }

    public function categoryService(): CategoryService
    {
        if ($this->categoryService === null) {
            $this->categoryService = new CategoryService($this->categoryAdminService());
        }

        return $this->categoryService;
    }

    public function mediaAdminService(): MediaAdminService
    {
        if ($this->mediaAdminService === null) {
            $this->mediaAdminService = new MediaAdminService(
                $this->mediaRepository(),
                $this->categoryRepository()
            );
        }

        return $this->mediaAdminService;
    }

    public function updateApplyService(): UpdateApplyService
    {
        if ($this->updateApplyService === null) {
            $this->updateApplyService = new UpdateApplyService(
                $this->projectRoot,
                $this->eventDispatcher(),
                $this->auditLog()
            );
        }

        return $this->updateApplyService;
    }

    public function settingsUpdateService(): SettingsUpdateService
    {
        if ($this->settingsUpdateService === null) {
            $this->settingsUpdateService = new SettingsUpdateService($this->settingsRepository());
        }

        return $this->settingsUpdateService;
    }

    public function importRunService(): ImportRunService
    {
        if ($this->importRunService === null) {
            $this->importRunService = new ImportRunService(
                $this->mediaImportService(),
                $this->importSettings(),
                $this->mediaAdminService()
            );
        }

        return $this->importRunService;
    }

    public function mediaItemApplicationService(): MediaItemApplicationService
    {
        if ($this->mediaItemApplicationService === null) {
            $this->mediaItemApplicationService = new MediaItemApplicationService(
                $this->mediaRepository(),
                $this->mediaAssetService(),
                $this->mediaAdminService(),
                $this->projectRoot
            );
        }

        return $this->mediaItemApplicationService;
    }

    public function eventDispatcher(): EventDispatcher
    {
        if ($this->eventDispatcher === null) {
            $this->eventDispatcher = new EventDispatcher();
        }

        return $this->eventDispatcher;
    }

    private function uploadScannerChain(): UploadScannerChain
    {
        if ($this->uploadScannerChainInstance === null) {
            $this->uploadScannerChainInstance = new UploadScannerChain($this->uploadScanners);
        }

        return $this->uploadScannerChainInstance;
    }

    /**
     * @template T of object
     * @param class-string<T> $id
     * @return T
     */
    private function resolveRegistered(string $id): object
    {
        if (! isset($this->bindingInstances[$id])) {
            if (! isset($this->bindings[$id])) {
                throw new RuntimeException('Unbekannte Service-ID: ' . $id);
            }
            $this->bindingInstances[$id] = ($this->bindings[$id])($this);
        }

        /** @var T */
        return $this->bindingInstances[$id];
    }

    private function tryResolveRegisteredClass(string $class): ?object
    {
        if (isset($this->bindings[$class]) || isset($this->bindingInstances[$class])) {
            return $this->resolveRegistered($class);
        }

        return null;
    }

    /**
     * Instanziiert einen Controller (oder andere Klasse) per Constructor-Injection.
     *
     * @template T of object
     * @param class-string<T> $class
     * @return T
     */
    public function make(HttpContext $http, string $class): object
    {
        $ref = new ReflectionClass($class);
        $ctor = $ref->getConstructor();
        if ($ctor === null) {
            /** @var T */
            return $ref->newInstance();
        }
        $args = [];
        foreach ($ctor->getParameters() as $param) {
            $args[] = $this->resolveConstructorParameter($param, $http);
        }

        /** @var T */
        return $ref->newInstanceArgs($args);
    }

    private function resolveConstructorParameter(ReflectionParameter $param, HttpContext $http): mixed
    {
        $type = $param->getType();
        if ($type === null) {
            if ($param->isDefaultValueAvailable()) {
                return $param->getDefaultValue();
            }
            throw new RuntimeException('Parameter ' . $param->getName() . ' ohne Typ ist nicht auflösbar.');
        }
        if ($type instanceof ReflectionUnionType) {
            throw new RuntimeException('Union-Typen für ' . $param->getName() . ' werden nicht unterstützt.');
        }
        if (! $type instanceof ReflectionNamedType) {
            throw new RuntimeException('Ungültiger Parametertyp für ' . $param->getName() . '.');
        }
        if ($type->isBuiltin()) {
            if ($param->isDefaultValueAvailable()) {
                return $param->getDefaultValue();
            }
            throw new RuntimeException('Builtin-Parameter ' . $param->getName() . ' ist nicht auflösbar.');
        }

        $name = $type->getName();

        return match ($name) {
            HttpContext::class => $http,
            Request::class => $http->request(),
            Container::class => $this,
            AuthService::class => $this->auth(),
            SettingsRepository::class => $this->settingsRepository(),
            MediaRepository::class => $this->mediaRepository(),
            CategoryRepository::class => $this->categoryRepository(),
            MediaUploadService::class => $this->mediaUploadService(),
            MediaImportService::class => $this->mediaImportService(),
            MediaAssetService::class => $this->mediaAssetService(),
            ImportSettings::class => $this->importSettings(),
            CategoryAdminService::class => $this->categoryAdminService(),
            MediaAdminService::class => $this->mediaAdminService(),
            UpdateApplyService::class => $this->updateApplyService(),
            SettingsUpdateService::class => $this->settingsUpdateService(),
            ImportRunService::class => $this->importRunService(),
            MediaItemApplicationService::class => $this->mediaItemApplicationService(),
            EventDispatcher::class => $this->eventDispatcher(),
            CategoryService::class => $this->categoryService(),
            AuditLog::class => $this->auditLog(),
            default => $this->tryResolveRegisteredClass($name)
                ?? throw new RuntimeException('Unbekannte Abhängigkeit: ' . $name . ' (' . $param->getName() . ').'),
        };
    }
}
