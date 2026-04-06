<?php

declare(strict_types=1);

namespace BSPhotoGalerie\Core;

use BSPhotoGalerie\Config\ImportSettings;
use BSPhotoGalerie\Config\MediaSettings;
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
use BSPhotoGalerie\Services\Import\MediaImportService;
use BSPhotoGalerie\Services\Media\MediaAssetService;
use BSPhotoGalerie\Services\Media\MediaUploadService;
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

    public function categoryAdminService(): CategoryAdminService
    {
        if ($this->categoryAdminService === null) {
            $this->categoryAdminService = new CategoryAdminService($this->categoryRepository());
        }

        return $this->categoryAdminService;
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
            default => throw new RuntimeException('Unbekannte Abhängigkeit: ' . $name . ' (' . $param->getName() . ').'),
        };
    }
}
