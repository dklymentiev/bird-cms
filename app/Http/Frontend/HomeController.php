<?php

declare(strict_types=1);

namespace App\Http\Frontend;

use App\Content\ArticleRepository;
use App\Content\MetricsRepository;
use App\Content\PageRepository;
use App\Http\HomeController as HomeDataAssembler;
use App\Theme\ThemeManager;

/**
 * `/` — homepage renderer.
 *
 * Wraps the existing `App\Http\HomeController` (renamed in scope to
 * `HomeDataAssembler` inside this file to disambiguate) and applies the
 * three post-assembly steps the procedural index.php was doing inline:
 *   - For personal/portfolio themes, splice `home.projects` into view data
 *     when configured.
 *   - Inject `recentPosts` (latest 10) and the full `config()` snapshot.
 *   - Step 5 fall-through: render `content/pages/home.md` as `$intro`
 *     inside the chosen template so operators can author a homepage intro
 *     without editing the theme.
 *
 * The `templateResolver` is the per-URL template override closure built in
 * public/index.php; we keep it injectable rather than re-implementing the
 * UrlMeta lookup because the dispatcher needs to share the same resolver
 * across every controller that renders through the theme.
 */
final class HomeController
{
    /** @param callable(string): string $templateResolver */
    public function __construct(
        private readonly ArticleRepository $articles,
        private readonly PageRepository $pages,
        private readonly MetricsRepository $metrics,
        private readonly ThemeManager $theme,
        /** @var list<string> */
        private readonly array $categoriesList,
        private $templateResolver,
    ) {
    }

    public function handle(): void
    {
        $assembler = new HomeDataAssembler($this->articles);
        $homeConfig = (array) config('home', []);
        $viewData = $assembler->getData($homeConfig, $this->categoriesList, $this->metrics);

        if (isset($homeConfig['projects'])) {
            $viewData['projects'] = $homeConfig['projects'];
        }
        $viewData['recentPosts'] = $this->articles->latest(10);
        $viewData['config'] = config();

        $homeIntro = $this->pages->find('home');
        $viewData['intro'] = $homeIntro['html'] ?? null;
        $viewData['introPage'] = $homeIntro;

        $resolve = $this->templateResolver;
        $this->theme->render($resolve('home'), $viewData);
    }
}
