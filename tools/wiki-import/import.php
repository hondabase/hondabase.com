<?php
declare(strict_types=1);

/**
 * One-time importer: pgmfi.org TWiki static mirror -> pgmfi_wiki_archive DB.
 *
 * Usage: php import.php [--dry-run]
 *
 * Idempotent: truncates the wiki tables and re-copies media on every real run.
 */

const SRC  = '/var/www/hondabase/www/tools/wiki-import/source/twiki';
const DEST = '/var/www/hondabase/www/public/pgmfi/wiki';
const BASE = '/pgmfi/wiki';
const FILES_ARCHIVE = '/var/www/hondabase/files/storage/archive';

$DRY = in_array('--dry-run', $argv, true);

// Webs: archive dir name => url segment
$WEBS = ['Home' => 'home', 'Library' => 'library'];

// TWiki chrome topics excluded by exact name. WebHome is special-cased
// (imported as the web landing page). WebGeek / XDEep are real topics.
$CHROME = [
    'Home' => [
        'WebChanges', 'WebIndex', 'WebNotify', 'WebPreferences', 'WebRss',
        'WebSearch', 'WebStatistics', 'WebTopicList',
        'TWikiAdminGroup', 'TWikiGroups', 'TWikiGuest', 'TWikiUsers',
        'TWikiVariables', 'TWikiWebHome',
    ],
    'Library' => [
        'WebChanges', 'WebIndex', 'WebPreferences', 'WebSearch', 'WebTopicList',
    ],
];

// Slugs that would collide with viewer routes/dirs.
$RESERVED_SLUGS = ['all', 'search', 'suggest', 'media', 'assets', 'inc', 'index'];

// ---------------------------------------------------------------------------
// helpers

function kebab(string $name): string
{
    $s = preg_replace('/(?<=[a-z])(?=[A-Z])|(?<=[A-Z0-9])(?=[A-Z][a-z])/', '-', $name);
    $s = preg_replace('/[^A-Za-z0-9]+/', '-', $s);
    return strtolower(trim(preg_replace('/-+/', '-', $s), '-'));
}

function spaced_title(string $name): string
{
    $s = preg_replace('/(?<=[a-z])(?=[A-Z])|(?<=[A-Z0-9])(?=[A-Z][a-z])/', ' ', $name);
    return trim(preg_replace('/[_\s]+/', ' ', $s));
}

function text_of_html(string $html): string
{
    $t = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML401, 'UTF-8');
    $t = str_replace("\u{00A0}", ' ', $t);
    return trim(preg_replace('/\s+/u', ' ', $t));
}

/** Extract content fragment + footer metadata from one archive page. */
function extract_page(string $path): ?array
{
    $raw = file_get_contents($path);
    if ($raw === false) {
        return null;
    }
    $utf8 = mb_convert_encoding($raw, 'UTF-8', 'ISO-8859-1');

    $searchPos = strpos($utf8, '<a href="WebSearch.html">Search</a>');
    if ($searchPos === false) {
        return null;
    }
    $formEnd = strpos($utf8, '</form>', $searchPos);
    if ($formEnd === false) {
        return null;
    }
    $start = $formEnd + strlen('</form>');

    $revPos = strrpos($utf8, '<b>Revision:</b>');
    if ($revPos === false || $revPos < $start) {
        return null;
    }
    $end = strrpos(substr($utf8, 0, $revPos), '</td></tr></table>');
    if ($end === false || $end < $start) {
        return null;
    }

    $fragment = substr($utf8, $start, $end - $start);

    // Strip the cosmetic empty signature table at the tail.
    $fragment = preg_replace(
        '#<table width="100%" border="0" cellpadding="3" cellspacing="0">\s*'
        . '<tr>\s*<td align="left">\s*<font size="-1">\s*</font></td>\s*</tr>\s*</table>#i',
        '',
        $fragment
    );

    // Footer metadata. The author may be plain text, a link, a dead-link span
    // or empty, so parse the revision cell as stripped text.
    $footer = substr($utf8, $revPos);
    $revision = $author = $revisedAt = null;
    if (preg_match('#<b>Revision:</b>(.*?)</FONT>#is', $footer, $m)) {
        $line = text_of_html($m[1]);
        if (preg_match('/^r([\d.]+) - (\d{1,2} \w{3} \d{4}) - (\d{2}:\d{2}) GMT(?: - (.*?))?(?: \?)?$/u', $line, $mm)) {
            $revision = $mm[1];
            $dt = DateTime::createFromFormat('j M Y H:i', $mm[2] . ' ' . $mm[3], new DateTimeZone('GMT'));
            $revisedAt = $dt ? $dt->format('Y-m-d H:i:s') : null;
            $author = trim($mm[4] ?? '');
            $author = preg_replace('/^Main\./', '', $author);
            $author = rtrim($author, '? ');
            $author = $author === '' ? null : $author;
        }
    }

    $parent = null;
    if (preg_match('#<B>Parents:</b>(.*?)</FONT>#is', $footer, $m)
        && preg_match_all('/href="([A-Za-z0-9_.-]+)\.html"/', $m[1], $pm)) {
        $parent = end($pm[1]); // immediate parent is the last crumb
    }

    return [
        'fragment'   => $fragment,
        'revision'   => $revision,
        'revised_at' => $revisedAt,
        'author'     => $author,
        'parent'     => $parent,
    ];
}

// ---------------------------------------------------------------------------
// Pass 1: enumerate + extract + classify

$pages = [];          // webdir => name => extract result
$markerFailures = []; // files where extraction markers were missing
foreach ($WEBS as $webDir => $webSlug) {
    foreach (glob(SRC . "/bin/view/$webDir/*.html") as $file) {
        $name = basename($file, '.html');
        $info = extract_page($file);
        if ($info === null) {
            $markerFailures[] = "$webDir/$name";
            continue;
        }
        $pages[$webDir][$name] = $info;
    }
}

// Stub detection (Home only): compare name-normalised text to a known stub.
$stubMarker = 'Personal Preferences (details in';
$refStub = $pages['Home']['18gsir'] ?? null;
if ($refStub === null) {
    fwrite(STDERR, "FATAL: reference stub Home/18gsir not found\n");
    exit(1);
}
$normalizeStub = function (string $fragment, string $name): string {
    $t = text_of_html($fragment);
    // Word-boundary replacement: short names like "D" or "Cc" must not be
    // replaced inside unrelated words or the comparison text gets mangled.
    $t = preg_replace('/\b' . preg_quote($name, '/') . '\b/iu', '@NAME@', $t);
    return preg_replace('/\s+/u', ' ', $t);
};
$refText = $normalizeStub($refStub['fragment'], '18gsir');

$status = [];      // webdir => name => 'import' | 'webhome' | 'stub' | 'chrome'
$stubReview = [];  // stub marker present but content differs -> imported, review
foreach ($pages as $webDir => $topics) {
    foreach ($topics as $name => $info) {
        $name = (string) $name; // numeric topic names become int keys
        if ($name === 'WebHome') {
            $status[$webDir][$name] = 'webhome';
        } elseif (in_array($name, $CHROME[$webDir], true)) {
            $status[$webDir][$name] = 'chrome';
        } elseif ($webDir === 'Home' && str_contains($info['fragment'], $stubMarker)) {
            if ($normalizeStub($info['fragment'], $name) === $refText) {
                $status[$webDir][$name] = 'stub';
            } else {
                $status[$webDir][$name] = 'import';
                $stubReview[] = "$webDir/$name";
            }
        } else {
            $status[$webDir][$name] = 'import';
        }
    }
}

// ---------------------------------------------------------------------------
// Pass 2: slugs for every topic (skipped ones too, for 404 messaging)

$slugs = []; // webdir => name => slug
foreach ($pages as $webDir => $topics) {
    $names = array_map('strval', array_keys($topics));
    sort($names, SORT_STRING);
    $taken = [];
    foreach ($names as $name) {
        $slug = kebab($name);
        if ($slug === '') {
            $slug = 'topic';
        }
        if (in_array($slug, $RESERVED_SLUGS, true)) {
            $slug .= '-topic';
        }
        if (isset($taken[$slug])) {
            $n = 2;
            while (isset($taken["$slug-$n"])) {
                $n++;
            }
            $slug = "$slug-$n";
        }
        $taken[$slug] = true;
        $slugs[$webDir][$name] = $slug;
    }
}

// ---------------------------------------------------------------------------
// Pass 3: DOM cleanup + link rewriting for kept topics

$linkStats = ['topic' => 0, 'webhome' => 0, 'list' => 0, 'dead' => 0,
              'unwrapped' => 0, 'removed' => 0, 'media' => 0, 'media-missing' => 0,
              'icon' => 0, 'external' => 0, 'forum' => 0];
$forumRefs = ['topic' => [], 'forum' => []]; // phpBB ids referenced from wiki pages

// Enumerate pub attachments and decide where each is served from: an
// identical file already in the curated files.hondabase.com collection,
// or our own media tree.
$mediaFiles = []; // [webDir, topicDir, filename, dest rel path, size, src rel path, external url|null]
$pubSizes = [];
foreach ($WEBS as $webDir => $webSlug) {
    $base = SRC . "/pub/$webDir";
    if (!is_dir($base)) {
        continue;
    }
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        /** @var SplFileInfo $f */
        if (!$f->isFile()) {
            continue;
        }
        $name = $f->getFilename();
        if (str_starts_with($name, '.') || preg_match('/\.ph(p|tml|ar)\d*$/i', $name)) {
            continue; // never ship anything PHP-FPM might execute
        }
        $rel = substr($f->getPathname(), strlen($base) + 1); // <Topic>/<file...>
        $topicDir = explode('/', $rel)[0];
        $mediaFiles[] = [$webDir, $topicDir, $name, "$webSlug/$rel", $f->getSize(), $rel, null];
        $pubSizes[$f->getSize()] = true;
    }
}
$dedupe = build_dedupe_map($pubSizes);
$PUB = []; // "<Web>/<Topic>/<file>" => serving URL
$dedupeCount = 0;
foreach ($mediaFiles as $i => [$webDir, $topicDir, , , , $srcRel]) {
    $external = $dedupe[sha1_file(SRC . "/pub/$webDir/$srcRel")] ?? null;
    if ($external !== null) {
        $mediaFiles[$i][6] = $external;
        $dedupeCount++;
    }
    $PUB["$webDir/$srcRel"] = $external
        ?? media_url($WEBS[$webDir], $topicDir, substr($srcRel, strlen($topicDir) + 1));
}
$deadPubRefs = [];

/** Resolve a topic reference to a URL, a dead-link, or chrome unwrap/removal. */
function resolve_topic(string $webDir, string $name, array $status, array $slugs, array $WEBS): array
{
    $webSlug = $WEBS[$webDir];
    if ($name === 'WebHome') {
        return ['kind' => 'webhome', 'url' => BASE . "/$webSlug/"];
    }
    if ($name === 'WebTopicList' || $name === 'WebIndex') {
        return ['kind' => 'list', 'url' => BASE . "/$webSlug/all"];
    }
    $st = $status[$webDir][$name] ?? null;
    if ($st === 'import') {
        return ['kind' => 'topic', 'url' => BASE . "/$webSlug/" . $slugs[$webDir][$name]];
    }
    if ($st === 'chrome') {
        return ['kind' => 'unwrap'];
    }
    if ($st === 'stub') {
        return ['kind' => 'dead', 'title' => 'not archived - page contained only the default TWiki user template'];
    }
    return ['kind' => 'dead', 'title' => 'not archived - page was never created on pgmfi.org'];
}

function media_url(string $webSlug, string $topicDir, string $file): string
{
    return BASE . '/media/' . implode('/', array_map('rawurlencode', explode('/', "$webSlug/$topicDir/$file")));
}

function admin_pdo(string $dbname): PDO
{
    $cnf = parse_ini_file('/root/.my.cnf', true);
    return new PDO("mysql:host=localhost;dbname=$dbname;charset=utf8mb4",
        $cnf['client']['user'] ?? 'root', $cnf['client']['password'] ?? '', [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
}

/**
 * Files already hosted in the curated files.hondabase.com collection (outside
 * the pgmfiorg mirror) are linked there instead of being served twice.
 * Returns sha1 => public download URL, prefiltered by candidate file sizes.
 */
function build_dedupe_map(array $pubSizes): array
{
    $pdo = admin_pdo('hondabase');
    $folders = $pdo->query('SELECT id, parent_id, name FROM folders')->fetchAll(PDO::FETCH_UNIQUE);
    $paths = [];
    $resolve = function (?int $id) use (&$resolve, &$paths, $folders): ?string {
        if ($id === null) {
            return '';
        }
        if (!isset($folders[$id])) {
            return null;
        }
        if (!array_key_exists($id, $paths)) {
            $parent = $folders[$id]['parent_id'];
            $base = $resolve($parent === null ? null : (int) $parent);
            $paths[$id] = $base === null ? null : ltrim($base . '/' . $folders[$id]['name'], '/');
        }
        return $paths[$id];
    };

    $map = [];
    foreach ($pdo->query("SELECT id, folder_id, name, size FROM files WHERE status = 'approved'") as $r) {
        if (!isset($pubSizes[(int) $r['size']])) {
            continue;
        }
        $dir = $resolve($r['folder_id'] === null ? null : (int) $r['folder_id']);
        if ($dir === null || $dir === 'pgmfiorg' || str_starts_with($dir, 'pgmfiorg/')) {
            continue;
        }
        $disk = FILES_ARCHIVE . '/' . ($dir === '' ? '' : "$dir/") . $r['name'];
        if (!is_file($disk)) {
            continue;
        }
        $map[sha1_file($disk)] ??= 'https://files.hondabase.com/download/' . $r['id'];
    }
    return $map;
}

function rrmdir(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($it as $f) {
        $f->isDir() ? rmdir($f->getPathname()) : unlink($f->getPathname());
    }
    rmdir($dir);
}

/** Replace an element with its children (unwrap). */
function dom_unwrap(DOMElement $el): void
{
    $parent = $el->parentNode;
    while ($el->firstChild) {
        $parent->insertBefore($el->firstChild, $el);
    }
    $parent->removeChild($el);
}

function clean_fragment(string $fragment, string $webDir, array $status, array $slugs,
                        array $WEBS, array &$linkStats, array &$deadPubRefs, string $topicName): string
{
    // TWiki emits <p /> as a separator; the HTML parser would treat it as an
    // opening <p> and swallow everything after it.
    $fragment = str_ireplace(['<p />', '<p/>'], '<p></p>', $fragment);

    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTML(
        '<?xml encoding="UTF-8"><div id="twiki-root">' . $fragment . '</div>',
        LIBXML_NOERROR | LIBXML_NONET
    );
    libxml_clear_errors();
    $xp = new DOMXPath($dom);
    $root = $xp->query('//div[@id="twiki-root"]')->item(0);
    if ($root === null) {
        return '';
    }

    // 1. Drop scripts/forms/comments and friends. Processing instructions
    // (PhpWiki "plugin" leftovers in two pages) render as garbage.
    foreach ($xp->query('//script | //noscript | //iframe | //form | //input | //textarea | //select | //comment() | //processing-instruction()', $root) as $n) {
        $n->parentNode?->removeChild($n);
    }
    foreach ($xp->query('//*[@*]', $root) as $el) {
        foreach (iterator_to_array($el->attributes) as $attr) {
            if (str_starts_with(strtolower($attr->name), 'on')) {
                $el->removeAttribute($attr->name);
            }
        }
    }

    // 2. Dead-topic spans -> <span class="dead-link">; drop the edit-? anchor.
    foreach ($xp->query('//span[contains(@style,"FFFFCE")]', $root) as $span) {
        $dead = $dom->createElement('span');
        $dead->setAttribute('class', 'dead-link');
        $dead->setAttribute('title', 'not archived - page was never created on pgmfi.org');
        $dead->textContent = str_replace("\u{00A0}", ' ', $span->textContent);
        $next = $span->nextSibling;
        while ($next instanceof DOMText && trim($next->textContent) === '') {
            $next = $next->nextSibling;
        }
        if ($next instanceof DOMElement && $next->tagName === 'a'
            && str_contains($next->getAttribute('href'), '/bin/edit/')) {
            $next->parentNode->removeChild($next);
        }
        $span->parentNode->replaceChild($dead, $span);
        $linkStats['dead']++;
    }

    // 3. Cloudflare email-protection anchors -> plain text.
    foreach (iterator_to_array($xp->query('//a[starts-with(@href,"/cdn-cgi/")]', $root)) as $a) {
        dom_unwrap($a);
    }

    // 4. Rewrite every remaining link and image.
    foreach (iterator_to_array($xp->query('//a[@href]', $root)) as $a) {
        if (!$a->parentNode) {
            continue; // already detached
        }
        $href = $a->getAttribute('href');
        $resolved = null;
        $anchor = '';

        if (preg_match('/^#/', $href)) {
            continue; // in-page anchor
        }
        if (preg_match('#^(?:\.\./(Home|Library)/)?([A-Za-z0-9_.-]+)\.html(\#.*)?$#', $href, $m)) {
            $resolved = resolve_topic(($m[1] ?? '') !== '' ? $m[1] : $webDir, $m[2], $status, $slugs, $WEBS);
            $anchor = $m[3] ?? '';
        } elseif (preg_match('#^https?://(?:(?:www\.)?pgmfi\.org|files\.boostednw\.com/www\.pgmfi\.org)/twiki/bin/view[^/]*/(Home|Library)/([A-Za-z0-9_]+)#', $href, $m)) {
            $resolved = resolve_topic($m[1], $m[2], $status, $slugs, $WEBS);
        } elseif (preg_match('#^https?://(?:(?:www\.)?pgmfi\.org|files\.boostednw\.com/www\.pgmfi\.org)/twiki/bin/view[^/]*/([A-Za-z0-9_]+)\.html$#', $href, $m)) {
            // mirror URL with the web segment flattened away -> current web
            $resolved = resolve_topic($webDir, $m[1], $status, $slugs, $WEBS);
        } elseif (preg_match('#^https?://forum\.pgmfi\.org/viewtopic\.php\?(?:[^"\#]*&)?t=(\d+)#', $href, $m)) {
            // phpBB ids are preserved 1:1 in the recovered forum archive
            $a->setAttribute('href', '/pgmfi/forum/topic.php?id=' . $m[1]);
            $GLOBALS['forumRefs']['topic'][] = (int) $m[1];
            $linkStats['forum']++;
            continue;
        } elseif (preg_match('#^https?://forum\.pgmfi\.org/viewforum\.php\?(?:[^"\#]*&)?f=(\d+)#', $href, $m)) {
            $a->setAttribute('href', '/pgmfi/forum/forum.php?id=' . $m[1]);
            $GLOBALS['forumRefs']['forum'][] = (int) $m[1];
            $linkStats['forum']++;
            continue;
        } elseif (preg_match('#^https?://forum\.pgmfi\.org#', $href)) {
            $a->setAttribute('href', '/pgmfi/forum/');
            $linkStats['forum']++;
            continue;
        } elseif (preg_match('#^https?://(?:www\.)?pgmfi\.org/phorum/download\.php#', $href)) {
            // files hosted on the pre-phpBB Phorum install were never mirrored
            $dead = $dom->createElement('span');
            $dead->setAttribute('class', 'dead-link');
            $dead->setAttribute('title', 'file was hosted on the old pgmfi.org phorum and was not recovered');
            $dead->textContent = str_replace("\u{00A0}", ' ', $a->textContent);
            $a->parentNode->replaceChild($dead, $a);
            $linkStats['dead']++;
            continue;
        } elseif (preg_match('#^https?://(?:www\.)?pgmfi\.org/phorum#', $href)
            || preg_match('#^(?:\.\./)+phorum/#', $href)) {
            // pre-phpBB Phorum threads have no mappable ids -> forum archive root
            $a->setAttribute('href', '/pgmfi/forum/');
            $linkStats['forum']++;
            continue;
        } elseif (preg_match('#^(?:\.\./)+~blundar/(.+)$#', $href, $m)) {
            $a->setAttribute('href', BASE . '/media/blundar/' . $m[1]);
            $linkStats['media']++;
            continue;
        } elseif (preg_match('#^(?:\.\./)+index\.html$#', $href)) {
            dom_unwrap($a); // old pgmfi.org front page, long gone
            $linkStats['unwrapped']++;
            continue;
        } elseif (preg_match('#(?:^(?:\.\./)+|/twiki/bin/)(?:edit|attach|rdiff|oops|view\.1)#', $href)
            || str_contains($href, 'banners.pgmfi.org')) {
            $txt = trim($a->textContent);
            if ($txt === '?' || strcasecmp($txt, 'Modify') === 0 || $txt === '') {
                $a->parentNode->removeChild($a);
                $linkStats['removed']++;
            } else {
                dom_unwrap($a);
                $linkStats['unwrapped']++;
            }
            continue;
        } elseif (preg_match('#^(?:\.\./)+pub/(Home|Library)/([^/]+)/(.+)$#', $href, $m)
            || preg_match('#^https?://(?:www\.)?pgmfi\.org/twiki/pub/(Home|Library)/([^/]+)/(.+)$#', $href, $m)) {
            $file = rawurldecode($m[3]);
            $pub = $GLOBALS['PUB']["{$m[1]}/{$m[2]}/$file"] ?? null;
            $a->setAttribute('href', $pub ?? media_url($WEBS[$m[1]], $m[2], $file));
            if ($pub !== null) {
                $linkStats['media']++;
            } else {
                $a->setAttribute('class', trim($a->getAttribute('class') . ' dead-file'));
                $a->setAttribute('title', 'file not recovered in the archive');
                $deadPubRefs[] = "{$m[1]}/{$m[2]}/$file (linked from $webDir/$topicName)";
                $linkStats['media-missing']++;
            }
            continue;
        } elseif (preg_match('#^(?:\.\./)+pub/icn/(.+)$#', $href, $m)) {
            $a->setAttribute('href', BASE . '/assets/icn/' . $m[1]);
            $linkStats['icon']++;
            continue;
        } elseif (preg_match('#^https?://#i', $href)) {
            $a->setAttribute('rel', 'nofollow noopener');
            $linkStats['external']++;
            continue;
        } else {
            continue; // mailto:, odd relative paths - leave untouched
        }

        // Apply a resolved topic reference.
        if ($resolved['kind'] === 'dead') {
            $dead = $dom->createElement('span');
            $dead->setAttribute('class', 'dead-link');
            $dead->setAttribute('title', $resolved['title']);
            $dead->textContent = str_replace("\u{00A0}", ' ', $a->textContent);
            $a->parentNode->replaceChild($dead, $a);
            $linkStats['dead']++;
        } elseif ($resolved['kind'] === 'unwrap') {
            dom_unwrap($a);
            $linkStats['unwrapped']++;
        } else {
            $a->setAttribute('href', $resolved['url'] . $anchor);
            $linkStats[$resolved['kind']]++;
        }
    }

    foreach (iterator_to_array($xp->query('//img[@src]', $root)) as $img) {
        $src = $img->getAttribute('src');
        if (preg_match('#^(?:\.\./)+pub/(Home|Library)/([^/]+)/(.+)$#', $src, $m)
            || preg_match('#^https?://(?:www\.)?pgmfi\.org/twiki/pub/(Home|Library)/([^/]+)/(.+)$#', $src, $m)) {
            $file = rawurldecode($m[3]);
            $pub = $GLOBALS['PUB']["{$m[1]}/{$m[2]}/$file"] ?? null;
            $img->setAttribute('src', $pub ?? media_url($WEBS[$m[1]], $m[2], $file));
            if ($pub !== null) {
                $linkStats['media']++;
            } else {
                $deadPubRefs[] = "{$m[1]}/{$m[2]}/$file (image in $webDir/$topicName)";
                $linkStats['media-missing']++;
            }
        } elseif (preg_match('#^(?:\.\./)+pub/icn/(.+)$#', $src, $m)) {
            $img->setAttribute('src', BASE . '/assets/icn/' . $m[1]);
            $linkStats['icon']++;
        } elseif (preg_match('#^(?:\.\./)+~blundar/(.+)$#', $src, $m)) {
            $img->setAttribute('src', BASE . '/media/blundar/' . $m[1]);
            $linkStats['media']++;
        } elseif (preg_match('#^(?:\.\./)+images/#', $src)) {
            // TWiki layout chrome image that leaked into content - drop it.
            $img->parentNode->removeChild($img);
        }
    }

    // 5. Strip presentational markup that fights the dark theme.
    foreach ($xp->query('//*[@bgcolor or @background or @color]', $root) as $el) {
        $el->removeAttribute('bgcolor');
        $el->removeAttribute('background');
        $el->removeAttribute('color');
    }
    foreach (iterator_to_array($xp->query('//font', $root)) as $font) {
        if ($font->parentNode) {
            dom_unwrap($font);
        }
    }

    $out = '';
    foreach ($root->childNodes as $child) {
        $out .= $dom->saveHTML($child);
    }
    return trim($out);
}

$rows = []; // topic rows ready for insert
foreach ($pages as $webDir => $topics) {
    foreach ($topics as $name => $info) {
        $name = (string) $name;
        $st = $status[$webDir][$name];
        if ($st !== 'import' && $st !== 'webhome') {
            continue;
        }
        $bodyHtml = clean_fragment($info['fragment'], $webDir, $status, $slugs,
            $WEBS, $linkStats, $deadPubRefs, $name);
        $rows[] = [
            'web'         => $WEBS[$webDir],
            'name'        => $name,
            'slug'        => $slugs[$webDir][$name],
            'title'       => $st === 'webhome' ? ($webDir === 'Home' ? 'Home' : 'Library') : spaced_title($name),
            'body_html'   => $bodyHtml,
            'body_text'   => text_of_html($bodyHtml),
            'author'      => $info['author'],
            'revision'    => $info['revision'],
            'revised_at'  => $info['revised_at'],
            'parent_name' => $info['parent'],
            'is_webhome'  => $st === 'webhome' ? 1 : 0,
        ];
    }
}

// ---------------------------------------------------------------------------
// Pass 4: media copy + DB writes

if (!$DRY) {
    // Rebuild the served media tree from scratch so deduplicated copies and
    // files removed upstream don't linger. Copy non-deduped attachments in.
    foreach (['home', 'library', 'blundar'] as $d) {
        rrmdir(DEST . "/media/$d");
    }
    foreach ($mediaFiles as [$webDir, $topicDir, $name, $destRel, $size, $srcRel, $external]) {
        if ($external !== null) {
            continue; // served by files.hondabase.com
        }
        $dst = DEST . "/media/$destRel";
        if (!is_dir(dirname($dst))) {
            mkdir(dirname($dst), 0755, true);
        }
        copy(SRC . "/pub/$webDir/$srcRel", $dst);
    }
    if (!is_dir(DEST . '/assets/icn')) {
        mkdir(DEST . '/assets/icn', 0755, true);
    }
    foreach (glob(SRC . '/pub/icn/*.gif') as $icon) {
        copy($icon, DEST . '/assets/icn/' . basename($icon));
    }

    // User-dir images referenced from content (Home/FooweesCar).
    $blundar = dirname(SRC) . '/~blundar';
    if (is_dir($blundar)) {
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($blundar, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            if (!$f->isFile() || preg_match('/\.ph(p|tml|ar)\d*$/i', $f->getFilename())) {
                continue;
            }
            $rel = substr($f->getPathname(), strlen($blundar) + 1);
            $dst = DEST . '/media/blundar/' . $rel;
            if (!is_dir(dirname($dst))) {
                mkdir(dirname($dst), 0755, true);
            }
            copy($f->getPathname(), $dst);
        }
    }

    $pdo = admin_pdo('pgmfi_wiki_archive');
    $pdo->exec('TRUNCATE topics');
    $pdo->exec('TRUNCATE attachments');
    $pdo->exec('TRUNCATE skipped_topics');

    $ins = $pdo->prepare(
        'INSERT INTO topics (web, name, slug, title, body_html, body_text, author,
            revision, revised_at, parent_name, is_webhome)
         VALUES (?,?,?,?,?,?,?,?,?,?,?)'
    );
    $topicIds = [];
    foreach ($rows as $r) {
        $ins->execute([$r['web'], $r['name'], $r['slug'], $r['title'], $r['body_html'],
            $r['body_text'], $r['author'], $r['revision'], $r['revised_at'],
            $r['parent_name'], $r['is_webhome']]);
        $topicIds[$r['web'] . '/' . $r['name']] = (int) $pdo->lastInsertId();
    }

    $insSkip = $pdo->prepare('INSERT INTO skipped_topics (web, name, slug, reason) VALUES (?,?,?,?)');
    foreach ($status as $webDir => $topics) {
        foreach ($topics as $name => $st) {
            if ($st === 'stub' || $st === 'chrome') {
                $insSkip->execute([$WEBS[$webDir], (string) $name, $slugs[$webDir][$name], $st]);
            }
        }
    }

    $insAtt = $pdo->prepare(
        'INSERT INTO attachments (web, topic_name, topic_id, filename, local_path, size_bytes)
         VALUES (?,?,?,?,?,?)'
    );
    foreach ($mediaFiles as [$webDir, $topicDir, $name, $destRel, $size, $srcRel, $external]) {
        $insAtt->execute([$WEBS[$webDir], $topicDir,
            $topicIds[$WEBS[$webDir] . '/' . $topicDir] ?? null, $name, $external ?? $destRel, $size]);
    }
}

// ---------------------------------------------------------------------------
// Summary

echo $DRY ? "=== DRY RUN (no writes) ===\n" : "=== IMPORT COMPLETE ===\n";
foreach ($status as $webDir => $topics) {
    $c = array_count_values($topics) + ['import' => 0, 'webhome' => 0, 'stub' => 0, 'chrome' => 0];
    echo sprintf("%-8s total=%d imported=%d (+%d webhome) stubs=%d chrome=%d\n",
        $webDir, count($pages[$webDir]), $c['import'], $c['webhome'], $c['stub'], $c['chrome']);
}
echo 'media files: ' . count($mediaFiles) . ' (' . $dedupeCount
    . " deduplicated to files.hondabase.com, " . (count($mediaFiles) - $dedupeCount) . " served locally)\n";
echo 'link rewrites: ' . json_encode($linkStats) . "\n";
if ($markerFailures) {
    echo "MARKER FAILURES (not imported!):\n  " . implode("\n  ", $markerFailures) . "\n";
}
if ($stubReview) {
    echo "STUB NEAR-MISSES (imported, review these):\n  " . implode("\n  ", $stubReview) . "\n";
}
if ($deadPubRefs) {
    echo "DEAD PUB REFERENCES (kept as dead-file/placeholder):\n  "
        . implode("\n  ", array_unique($deadPubRefs)) . "\n";
}

// Cross-check phpBB ids referenced from wiki pages against the forum archive.
if (!$DRY && ($forumRefs['topic'] || $forumRefs['forum'])) {
    foreach (['topic' => 'topics', 'forum' => 'forums'] as $kind => $table) {
        $ids = array_unique($forumRefs[$kind]);
        if (!$ids) {
            continue;
        }
        $in = implode(',', $ids);
        $found = $pdo->query(
            "SELECT {$kind}_id FROM pgmfi_forum_archive.$table WHERE {$kind}_id IN ($in)"
        )->fetchAll(PDO::FETCH_COLUMN);
        $missing = array_diff($ids, array_map('intval', $found));
        echo "forum {$kind} links: " . count($ids) . ' referenced, '
            . (count($missing) ? 'MISSING in forum archive: ' . implode(',', $missing) : 'all exist') . "\n";
    }
}
