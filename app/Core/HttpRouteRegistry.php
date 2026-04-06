<?php

declare(strict_types=1);

namespace BSPhotoGalerie\Core;

use BSPhotoGalerie\Controllers\Admin\CategoryController;
use BSPhotoGalerie\Controllers\Admin\DashboardController;
use BSPhotoGalerie\Controllers\Admin\ImportController;
use BSPhotoGalerie\Controllers\Admin\LoginController;
use BSPhotoGalerie\Controllers\Admin\MediaController;
use BSPhotoGalerie\Controllers\Admin\SettingsController;
use BSPhotoGalerie\Controllers\Admin\UpdateController;
use BSPhotoGalerie\Controllers\GalleryController;
use BSPhotoGalerie\Controllers\HomeController;
use BSPhotoGalerie\Controllers\ThumbController;
use FastRoute\Dispatcher;
use FastRoute\RouteCollector;

use function FastRoute\simpleDispatcher;

/**
 * Zentrale FastRoute-Definitionen (entzogen aus {@see Application} für schlankeren Front-Controller).
 */
final class HttpRouteRegistry
{
    /**
     * @return Dispatcher
     */
    public static function build(): Dispatcher
    {
        $R = static fn (string $auth, array $handler): array => ['handler' => $handler, 'auth' => $auth];

        return simpleDispatcher(function (RouteCollector $r) use ($R): void {
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
                $r->addRoute('POST', '/media/bulk-refresh-exif', $R('admin', [MediaController::class, 'bulkRefreshExif']));
                $r->addRoute('POST', '/media/inline-title', $R('admin', [MediaController::class, 'inlineTitle']));
                $r->addRoute('GET', '/media/{id:\d+}/edit', $R('admin', [MediaController::class, 'edit']));
                $r->addRoute('POST', '/media/{id:\d+}/update', $R('admin', [MediaController::class, 'update']));
                $r->addRoute('POST', '/media/{id:\d+}/refresh-exif', $R('admin', [MediaController::class, 'refreshExif']));
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
    }
}
