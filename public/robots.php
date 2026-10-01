<?php
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';

header('Content-Type: text/plain; charset=UTF-8');

$sitemapUrl = absolute_url($siteUrl, '/sitemap.xml');

// Default: everyone may crawl the public site, never the admin panel.
echo "User-agent: *\n";
echo "Disallow: /admin/\n";
echo "\n";

// Explicitly welcome known AI/answer-engine crawlers — this site wants to
// be discoverable and citable through AI-powered search, not just
// traditional search engines.
$aiCrawlers = [
    'GPTBot',            // OpenAI (training)
    'ChatGPT-User',      // OpenAI (live browsing/search)
    'Google-Extended',   // Google (AI features, separate from Googlebot)
    'CCBot',             // Common Crawl (feeds many AI models)
    'anthropic-ai',
    'ClaudeBot',         // Anthropic
    'PerplexityBot',     // Perplexity AI search
    'Applebot-Extended', // Apple (AI features)
];

foreach ($aiCrawlers as $agent) {
    echo "User-agent: $agent\n";
    echo "Disallow: /admin/\n";
    echo "\n";
}

echo "Sitemap: $sitemapUrl\n";
