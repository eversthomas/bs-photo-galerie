<?php

declare(strict_types=1);

namespace BSPhotoGalerie\Controllers;

use BSPhotoGalerie\Core\CsrfToken;
use BSPhotoGalerie\Core\HttpContext;

/**
 * Gemeinsame Hilfen für Controller (Views, Weiterleitung).
 */
abstract class BaseController
{
    public function __construct(
        protected HttpContext $http
    ) {
    }

    protected function render(string $template, array $data = [], ?string $layout = null): void
    {
        $data['app'] = $this->http;
        if (! array_key_exists('csrfToken', $data)) {
            $data['csrfToken'] = CsrfToken::token();
        }
        $data += ['flash' => []];

        extract($data, EXTR_SKIP);
        $root = $this->http->root();
        $tpl = $root . '/templates/' . $template . '.php';

        if (! is_file($tpl)) {
            throw new \RuntimeException('Vorlage fehlt: ' . $template);
        }

        header('Content-Type: text/html; charset=utf-8');

        if ($layout === null) {
            require $tpl;

            return;
        }

        $layoutFile = $root . '/templates/' . $layout . '.php';
        if (! is_file($layoutFile)) {
            throw new \RuntimeException('Layout fehlt: ' . $layout);
        }

        ob_start();
        require $tpl;
        $content = ob_get_clean();
        require $layoutFile;
    }
}
