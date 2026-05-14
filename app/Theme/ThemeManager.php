<?php

declare(strict_types=1);

namespace App\Theme;

use App\Support\Config;
use InvalidArgumentException;

final class ThemeManager
{
    public function __construct(
        private readonly string $themesPath,
        private string $activeTheme
    ) {
        if (!is_dir($this->themePath())) {
            throw new InvalidArgumentException("Theme '{$this->activeTheme}' not found at " . $this->themePath());
        }
    }

    public function active(): string
    {
        return $this->activeTheme;
    }

    public function path(string $relative = ''): string
    {
        $path = rtrim($this->themesPath, '/') . '/' . $this->activeTheme;
        return $relative === '' ? $path : $path . '/' . ltrim($relative, '/');
    }

    public function asset(string $path): string
    {
        $baseUrl = rtrim(Config::get('site_url', ''), '/');
        $assetPath = '/assets/' . $this->activeTheme . '/' . ltrim($path, '/');

        if ($baseUrl === '') {
            return $assetPath;
        }

        return $baseUrl . $assetPath;
    }

    public function render(string $view, array $data = [], ?string $layout = null): void
    {
        $viewPath = $this->path('views/' . $view . '.php');
        if (!file_exists($viewPath)) {
            // Engine-bundled fallback: when the active theme is missing
            // a view (e.g. `search`, `category`), try the canonical
            // engine `tailwind` theme. This keeps standard routes from
            // 500ing on themes that forgot to ship every view, while
            // letting any theme override by providing its own file.
            // The site layout (base.php) still wraps the fallback content,
            // so site chrome (header/footer/nav) remains theme-styled.
            $fallback = defined('ENGINE_THEMES_PATH')
                ? rtrim(ENGINE_THEMES_PATH, '/') . '/tailwind/views/' . $view . '.php'
                : null;
            if ($fallback !== null && file_exists($fallback) && $this->activeTheme !== 'tailwind') {
                $viewPath = $fallback;
            } else {
                throw new InvalidArgumentException("View '{$view}' not found for theme '{$this->activeTheme}'");
            }
        }

        $layoutPath = $layout ? $this->path($layout) : $this->path('layouts/base.php');

        $viewContext = [];
        $viewData = array_merge($data, ['theme' => $this, 'config' => Config::all()]);
        $content = $this->capture($viewPath, $viewData, $viewContext);

        $shared = array_merge($data, [
            'content' => $content,
            'theme' => $this,
            'config' => Config::all(),
            'meta' => $viewContext['meta'] ?? [],
            'structuredData' => $viewContext['structuredData'] ?? [],
            'pageTitle' => $viewContext['pageTitle'] ?? null,
            'breadcrumbItems' => $viewContext['breadcrumbItems'] ?? [],
            // Layout flags views can set to influence chrome (e.g. base.php
            // toggles `.layout-full` + sidebar partial when the view sets
            // $noSidebar=true). Without propagating these, layout always
            // renders the sidebar even when the view explicitly opts out.
            'noSidebar' => $viewContext['noSidebar'] ?? false,
            'showTwitter' => $viewContext['showTwitter'] ?? false,
        ]);

        if ($layoutPath && file_exists($layoutPath)) {
            echo $this->capture($layoutPath, $shared);
            return;
        }

        echo $content;
    }

    public function partial(string $partial, array $data = []): void
    {
        $partialPath = $this->path('partials/' . $partial . '.php');
        if (!file_exists($partialPath)) {
            return;
        }

        echo $this->capture($partialPath, $data);
    }

    private function capture(string $path, array $data, array &$viewContext = []): string
    {
        extract($data, EXTR_SKIP);
        ob_start();
        require $path;
        $content = (string) ob_get_clean();

        $available = get_defined_vars();
        $viewContext = array_intersect_key(
            $available,
            array_flip(['meta', 'structuredData', 'pageTitle', 'breadcrumbItems', 'noSidebar', 'showTwitter'])
        );

        return $content;
    }

    private function themePath(): string
    {
        return $this->themesPath . '/' . $this->activeTheme;
    }
}
