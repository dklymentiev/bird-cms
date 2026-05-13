<?php
declare(strict_types=1);

header("Content-Type: application/json");
header("Cache-Control: public, max-age=300");

require __DIR__ . "/../../bootstrap.php";

use App\Content\ArticleRepository;

$query = trim($_GET["q"] ?? "");

if (strlen($query) < 2) {
    echo json_encode(["results" => []]);
    exit;
}

$repository = new ArticleRepository(config("articles_dir"));
$allArticles = $repository->all();

$queryLower = mb_strtolower($query);
$results = [];

foreach ($allArticles as $article) {
    $title = mb_strtolower($article["title"] ?? "");
    $description = mb_strtolower($article["description"] ?? "");
    $tags = array_map("mb_strtolower", $article["tags"] ?? []);
    
    $score = 0;
    
    // Title match (highest priority)
    if (str_contains($title, $queryLower)) {
        $score += 100;
        if (str_starts_with($title, $queryLower)) {
            $score += 50;
        }
    }
    
    // Description match
    if (str_contains($description, $queryLower)) {
        $score += 30;
    }
    
    // Tag match
    foreach ($tags as $tag) {
        if (str_contains($tag, $queryLower)) {
            $score += 20;
            break;
        }
    }
    
    if ($score > 0) {
        $results[] = [
            "title" => $article["title"],
            "description" => substr($article["description"] ?? "", 0, 120) . "...",
            "url" => "/" . $article["category"] . "/" . $article["slug"],
            "category" => ucfirst($article["category"]),
            "score" => $score,
        ];
    }
}

// Sort by score descending
usort($results, fn($a, $b) => $b["score"] <=> $a["score"]);

// Limit results
$results = array_slice($results, 0, 8);

// Remove score from output
$results = array_map(fn($r) => array_diff_key($r, ["score" => 1]), $results);

echo json_encode(["results" => $results]);
