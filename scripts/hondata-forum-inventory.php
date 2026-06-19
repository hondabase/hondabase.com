#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Build a local research inventory of Hondata forum topics.
 *
 * Default mode stores one JSON metadata file per topic, plus small excerpts. It intentionally does
 * not mirror full post bodies unless --include-post-text is passed by a user who has permission to
 * archive that content.
 */

$options = getopt('', [
    'start-url::',
    'out::',
    'delay-ms::',
    'max-pages::',
    'max-topics::',
    'username-env::',
    'password-env::',
    'include-post-text',
    'help',
]);

if (isset($options['help'])) {
    fwrite(STDOUT, <<<'TXT'
Usage:
  HONDATA_USERNAME=... HONDATA_PASSWORD=... php scripts/hondata-forum-inventory.php [options]

Options:
  --start-url=URL        Forum index URL. Default: https://www.hondata.com/forum/
  --out=DIR             Output directory. Default: storage/app/imports/hondata-forum
  --delay-ms=N          Delay between requests. Default: 1500
  --max-pages=N         Stop after N fetched pages. Useful for dry runs.
  --max-topics=N        Stop after N topic files. Useful for dry runs.
  --username-env=NAME   Environment variable containing the forum username. Default: HONDATA_USERNAME
  --password-env=NAME   Environment variable containing the forum password. Default: HONDATA_PASSWORD
  --include-post-text   Store full visible post text. Use only with permission to archive the forum content.

TXT);
    exit(0);
}

$startUrl = (string) ($options['start-url'] ?? 'https://www.hondata.com/forum/');
$outDir = rtrim((string) ($options['out'] ?? __DIR__.'/../storage/app/imports/hondata-forum'), '/');
$delayMs = max(250, (int) ($options['delay-ms'] ?? 1500));
$maxPages = isset($options['max-pages']) ? max(1, (int) $options['max-pages']) : null;
$maxTopics = isset($options['max-topics']) ? max(1, (int) $options['max-topics']) : null;
$usernameEnv = (string) ($options['username-env'] ?? 'HONDATA_USERNAME');
$passwordEnv = (string) ($options['password-env'] ?? 'HONDATA_PASSWORD');
$includePostText = array_key_exists('include-post-text', $options);

$base = parse_url($startUrl);
if (! isset($base['scheme'], $base['host'])) {
    fail("Invalid --start-url: {$startUrl}");
}

ensureDirectory($outDir);
ensureDirectory("{$outDir}/topics");

$cookieJar = "{$outDir}/cookies.txt";
$client = new HttpClient($cookieJar, $delayMs);
$robots = $client->get($base['scheme'].'://'.$base['host'].'/robots.txt');
if ($robots['status'] >= 200 && $robots['status'] < 300 && ! robotsAllows((string) $robots['body'], '/forum/')) {
    fail('robots.txt disallows /forum/ for generic crawlers; stopping.');
}

$username = getenv($usernameEnv) ?: null;
$password = getenv($passwordEnv) ?: null;
if ($username !== null && $password !== null) {
    login($client, $startUrl, $username, $password);
} else {
    fwrite(STDERR, "No {$usernameEnv}/{$passwordEnv} found; crawling public pages only.\n");
}

$forumQueue = [$startUrl];
$seenForums = [];
$seenTopicIds = [];
$pageCount = 0;
$topicCount = 0;
$manifest = fopen("{$outDir}/manifest.jsonl", 'ab');
if ($manifest === false) {
    fail("Unable to open {$outDir}/manifest.jsonl");
}

while ($forumQueue !== []) {
    $forumUrl = array_shift($forumQueue);
    if ($forumUrl === null || isset($seenForums[$forumUrl])) {
        continue;
    }
    $seenForums[$forumUrl] = true;

    foreach (crawlForumPages($client, $forumUrl, $base['scheme'].'://'.$base['host']) as $page) {
        $pageCount++;
        if ($maxPages !== null && $pageCount > $maxPages) {
            break 2;
        }

        foreach ($page['forums'] as $url) {
            if (! isset($seenForums[$url])) {
                $forumQueue[] = $url;
            }
        }

        foreach ($page['topics'] as $topic) {
            $topicId = $topic['topic_id'] ?? sha1($topic['url']);
            if (isset($seenTopicIds[$topicId])) {
                continue;
            }
            $seenTopicIds[$topicId] = true;

            $topicData = fetchTopic($client, $topic['url'], $topic, $includePostText);
            $file = topicFilename($topicData);
            file_put_contents("{$outDir}/topics/{$file}", json_encode($topicData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
            fwrite($manifest, json_encode([
                'topic_id' => $topicData['topic_id'],
                'title' => $topicData['title'],
                'url' => $topicData['url'],
                'file' => "topics/{$file}",
                'convertibility_signals' => $topicData['convertibility_signals'],
            ], JSON_UNESCAPED_SLASHES)."\n");
            $topicCount++;

            if ($maxTopics !== null && $topicCount >= $maxTopics) {
                break 3;
            }
        }
    }
}

fclose($manifest);

file_put_contents("{$outDir}/run.json", json_encode([
    'start_url' => $startUrl,
    'generated_at' => gmdate('c'),
    'pages_fetched' => $pageCount,
    'topics_written' => $topicCount,
    'include_post_text' => $includePostText,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");

fwrite(STDOUT, "Wrote {$topicCount} topic inventories to {$outDir}/topics\n");

final class HttpClient
{
    public function __construct(
        private readonly string $cookieJar,
        private readonly int $delayMs,
    ) {}

    public function get(string $url): array
    {
        $this->pause();

        return $this->request('GET', $url);
    }

    public function post(string $url, array $fields): array
    {
        $this->pause();

        return $this->request('POST', $url, http_build_query($fields));
    }

    private function request(string $method, string $url, ?string $body = null): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_COOKIEFILE => $this->cookieJar,
            CURLOPT_COOKIEJAR => $this->cookieJar,
            CURLOPT_ENCODING => '',
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HEADER => false,
            CURLOPT_HTTPHEADER => ['Accept: text/html,application/xhtml+xml'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 45,
            CURLOPT_USERAGENT => 'HondabaseResearchBot/0.1 (+https://www.hondabase.com/)',
        ]);
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body ?? '');
        }

        $response = curl_exec($ch);
        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            fail("HTTP {$method} failed for {$url}: {$error}");
        }

        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $effectiveUrl = (string) curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        curl_close($ch);

        return ['status' => $status, 'url' => $effectiveUrl, 'body' => (string) $response];
    }

    private function pause(): void
    {
        usleep($this->delayMs * 1000);
    }
}

function login(HttpClient $client, string $startUrl, string $username, string $password): void
{
    $loginUrl = absoluteUrl('ucp.php?mode=login', $startUrl);
    $page = $client->get($loginUrl);
    $fields = parseFirstFormFields((string) $page['body']);
    $fields['username'] = $username;
    $fields['password'] = $password;
    $fields['login'] = 'Login';

    $response = $client->post($loginUrl, $fields);
    if ($response['status'] >= 400) {
        fail('Login request failed with HTTP '.$response['status']);
    }
    fwrite(STDERR, "Login request completed; continuing crawl.\n");
}

function crawlForumPages(HttpClient $client, string $forumUrl, string $origin): Generator
{
    $nextUrl = $forumUrl;
    $seenPages = [];

    while ($nextUrl !== null && ! isset($seenPages[$nextUrl])) {
        $seenPages[$nextUrl] = true;
        $response = $client->get($nextUrl);
        if ($response['status'] >= 400) {
            fwrite(STDERR, "Skipping {$nextUrl}: HTTP {$response['status']}\n");
            return;
        }

        $dom = htmlDom((string) $response['body']);
        $xpath = new DOMXPath($dom);
        $forums = [];
        $topics = [];
        $next = null;

        foreach ($xpath->query('//a[@href]') as $link) {
            $href = trim((string) $link->getAttribute('href'));
            $text = cleanText($link->textContent);
            $url = absoluteUrl($href, (string) $response['url']);
            if (! str_starts_with($url, $origin)) {
                continue;
            }
            if (preg_match('/viewforum\.php\?[^#]*\bf=(\d+)/', $url)) {
                $forums[$url] = $url;
            }
            if (preg_match('/viewtopic\.php\?[^#]*\bt=(\d+)/', $url, $match)) {
                $topics[$match[1]] = [
                    'topic_id' => $match[1],
                    'title' => $text,
                    'url' => canonicalTopicUrl($url),
                ];
            }
            if (preg_match('/\bnext\b|›|»/iu', $text) && str_contains($url, 'start=')) {
                $next = $url;
            }
        }

        yield [
            'url' => (string) $response['url'],
            'forums' => array_values($forums),
            'topics' => array_values($topics),
        ];

        $nextUrl = $next;
    }
}

function fetchTopic(HttpClient $client, string $url, array $listingTopic, bool $includePostText): array
{
    $response = $client->get($url);
    if ($response['status'] >= 400) {
        return $listingTopic + [
            'fetched_at' => gmdate('c'),
            'http_status' => $response['status'],
            'posts' => [],
            'convertibility_signals' => [],
        ];
    }

    $dom = htmlDom((string) $response['body']);
    $xpath = new DOMXPath($dom);
    $title = firstText($xpath, [
        '//h2[contains(@class, "topic-title")]',
        '//h1',
        '//title',
    ]) ?: $listingTopic['title'];
    $posts = [];

    foreach ($xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " post ")]') as $postNode) {
        $author = cleanText(firstText($xpath, [
            './/*[contains(@class, "author")]',
            './/*[contains(@class, "username")]',
        ], $postNode) ?? '');
        $body = cleanText(firstText($xpath, [
            './/*[contains(@class, "content")]',
            './/*[contains(@class, "postbody")]',
        ], $postNode) ?? '');
        if ($body === '') {
            continue;
        }

        $posts[] = array_filter([
            'author' => $author !== '' ? $author : null,
            'excerpt' => mb_substr($body, 0, 700),
            'text' => $includePostText ? $body : null,
        ], fn ($value) => $value !== null);
    }

    $topicId = $listingTopic['topic_id'] ?? topicIdFromUrl($url);
    $data = [
        'topic_id' => $topicId,
        'title' => cleanTopicTitle($title),
        'url' => canonicalTopicUrl((string) $response['url']),
        'source' => 'hondata.com/forum',
        'fetched_at' => gmdate('c'),
        'http_status' => $response['status'],
        'posts_sampled' => count($posts),
        'post_text_included' => $includePostText,
        'posts' => $posts,
    ];
    $data['convertibility_signals'] = classifyTopic($data);

    return $data;
}

function classifyTopic(array $topic): array
{
    $text = mb_strtolower(($topic['title'] ?? '').' '.implode(' ', array_map(
        fn (array $post) => $post['excerpt'] ?? '',
        $topic['posts'] ?? [],
    )));
    $signals = [];

    foreach ([
        'how_to' => ['how to', 'install', 'setup', 'wiring', 'calibrate', 'configure', 'conversion'],
        'troubleshooting' => ['problem', 'issue', 'error', 'code', 'won\'t', 'fails', 'stall', 'misfire'],
        'reference' => ['pinout', 'map', 'sensor', 'ecu', 'kpro', 's300', 'flashpro', 'boost'],
    ] as $signal => $needles) {
        foreach ($needles as $needle) {
            if (str_contains($text, $needle)) {
                $signals[] = $signal;
                break;
            }
        }
    }

    return array_values(array_unique($signals));
}

function parseFirstFormFields(string $html): array
{
    $dom = htmlDom($html);
    $xpath = new DOMXPath($dom);
    $fields = [];

    foreach ($xpath->query('//form[1]//input[@name]') as $input) {
        $fields[(string) $input->getAttribute('name')] = (string) $input->getAttribute('value');
    }

    return $fields;
}

function robotsAllows(string $robots, string $path): bool
{
    $applies = false;
    foreach (preg_split('/\R/', $robots) ?: [] as $line) {
        $line = trim(preg_replace('/#.*/', '', $line) ?? '');
        if ($line === '') {
            continue;
        }
        if (stripos($line, 'User-agent:') === 0) {
            $agent = trim(substr($line, strlen('User-agent:')));
            $applies = $agent === '*';
            continue;
        }
        if ($applies && stripos($line, 'Disallow:') === 0) {
            $rule = trim(substr($line, strlen('Disallow:')));
            if ($rule !== '' && str_starts_with($path, $rule)) {
                return false;
            }
        }
    }

    return true;
}

function htmlDom(string $html): DOMDocument
{
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="utf-8" ?>'.$html, LIBXML_NOERROR | LIBXML_NOWARNING);
    libxml_clear_errors();

    return $dom;
}

function firstText(DOMXPath $xpath, array $queries, ?DOMNode $context = null): ?string
{
    foreach ($queries as $query) {
        $nodes = $xpath->query($query, $context);
        if ($nodes !== false && $nodes->length > 0) {
            $text = cleanText((string) $nodes->item(0)?->textContent);
            if ($text !== '') {
                return $text;
            }
        }
    }

    return null;
}

function cleanText(string $text): string
{
    return trim(preg_replace('/\s+/u', ' ', html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '');
}

function cleanTopicTitle(string $title): string
{
    return preg_replace('/\s+-\s+.*$/', '', cleanText($title)) ?? cleanText($title);
}

function canonicalTopicUrl(string $url): string
{
    $parts = parse_url($url);
    if (! isset($parts['scheme'], $parts['host'], $parts['path'])) {
        return $url;
    }
    parse_str($parts['query'] ?? '', $query);
    $kept = [];
    foreach (['f', 't'] as $key) {
        if (isset($query[$key])) {
            $kept[$key] = $query[$key];
        }
    }

    return $parts['scheme'].'://'.$parts['host'].$parts['path'].($kept !== [] ? '?'.http_build_query($kept) : '');
}

function topicIdFromUrl(string $url): string
{
    return preg_match('/[?&]t=(\d+)/', $url, $match) ? $match[1] : sha1($url);
}

function topicFilename(array $topic): string
{
    $slug = strtolower((string) preg_replace('/[^a-z0-9]+/i', '-', $topic['title'] ?? 'topic'));
    $slug = trim($slug, '-') ?: 'topic';

    return $slug.'-'.$topic['topic_id'].'.json';
}

function absoluteUrl(string $href, string $baseUrl): string
{
    if (preg_match('#^https?://#i', $href)) {
        return $href;
    }
    $base = parse_url($baseUrl);
    if (! isset($base['scheme'], $base['host'])) {
        return $href;
    }
    $root = $base['scheme'].'://'.$base['host'];
    if (str_starts_with($href, '/')) {
        return $root.$href;
    }
    $path = $base['path'] ?? '/';
    $dir = preg_replace('#/[^/]*$#', '/', $path) ?? '/';

    return $root.$dir.$href;
}

function ensureDirectory(string $path): void
{
    if (! is_dir($path) && ! mkdir($path, 0775, true) && ! is_dir($path)) {
        fail("Unable to create {$path}");
    }
}

function fail(string $message): never
{
    fwrite(STDERR, $message."\n");
    exit(1);
}
