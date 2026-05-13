<?php

declare(strict_types=1);

namespace App\Http\Frontend;

use App\Content\ArticleRepository;

/**
 * Generic /llms.txt renderer for AI search engines (Perplexity, ChatGPT, etc).
 *
 * Lists every published article grouped by its declared `type` field. No
 * hardcoded taxonomy: types come from each article's meta.yaml as-is, so a
 * site that publishes guides + comparisons + how-tos gets sections for
 * exactly those types and nothing else.
 *
 * Sites that need a custom voice (editorial persona, review-methodology
 * boilerplate, etc.) should override the /llms.txt route in their
 * site-level public/index.php before the dispatcher reaches this
 * controller.
 */
final class LlmsTxtController
{
    public function __construct(
        private readonly ArticleRepository $articles,
        private readonly string $articleUrlPrefix,
    ) {
    }

    public function handle(): void
    {
        header('Content-Type: text/plain; charset=utf-8');

        $siteUrl         = rtrim((string) config('site_url'), '/');
        $siteName        = (string) (config('site_name')        ?? 'Site');
        $siteDescription = (string) (config('site_description') ?? '');

        echo "# {$siteName}\n\n";
        if ($siteDescription !== '') {
            echo "> {$siteDescription}\n\n";
        }

        $byType = [];
        foreach ($this->articles->all() as $article) {
            $type = trim((string) ($article['type'] ?? 'article'));
            if ($type === '') {
                $type = 'article';
            }
            // Compose URL from siteUrl + prefix + cat/slug rather than
            // trusting $article['url'] — the repository's canonical URL
            // does not honour the `articles_prefix` config (covered by
            // LlmsTxtControllerTest::testArticleUrlPrefixIsHonored).
            $byType[$type][] = [
                'title' => $article['title'] ?? $article['slug'],
                'url'   => $siteUrl . $this->articleUrlPrefix . '/' . $article['category'] . '/' . $article['slug'],
                'description' => (string) ($article['description'] ?? ''),
            ];
        }

        ksort($byType);
        foreach ($byType as $type => $items) {
            echo '## ' . ucfirst($type) . "\n\n";
            foreach (array_slice($items, 0, 20) as $item) {
                $desc = $item['description'] !== ''
                    ? ': ' . mb_substr($item['description'], 0, 120)
                    : '';
                echo "- [{$item['title']}]({$item['url']}){$desc}\n";
            }
            echo "\n";
        }

        echo "## Contact\n\n";
        echo "Website: {$siteUrl}\n";
    }
}
