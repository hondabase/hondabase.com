<?php

namespace App\Services;

use App\Markdown\GithubAlertExtension;
use App\Markdown\MarkdownNormalizer;
use Illuminate\Support\Str;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\Extension\HeadingPermalink\HeadingPermalinkExtension;
use League\CommonMark\MarkdownConverter;
use Symfony\Component\Yaml\Yaml;

/**
 * Reads articles from the local clone of hondabase/articles.
 *
 * Convention (authoritative, from the repo):
 *   content/<type>/<category>/<slug>/<slug>.md  + co-located image assets
 *   category = folder; title = first H1 (or `title:` frontmatter); frontmatter optional.
 */
class ArticleService
{
    private string $root;

    private MarkdownConverter $converter;

    public function __construct(
        private ArticleAuthorService $authors,
        private MarkdownNormalizer $markdown,
    ) {
        $this->root = rtrim((string) config('hondabase.content_path'), '/');

        $env = new Environment([
            'html_input' => 'escape',
            'allow_unsafe_links' => false,
            'heading_permalink' => [
                'html_class' => 'heading-anchor',
                'id_prefix' => '',
                'fragment_prefix' => '',
                'insert' => 'after',
                'min_heading_level' => 2,
                'symbol' => '#',
                'aria_hidden' => true,
            ],
            'table' => [
                'wrap' => [
                    'enabled' => true,
                    'tag' => 'div',
                    'attributes' => [
                        'class' => 'table-scroll',
                        'role' => 'region',
                        'tabindex' => '0',
                        'aria-label' => 'Scrollable table',
                    ],
                ],
            ],
        ]);
        $env->addExtension(new CommonMarkCoreExtension);
        $env->addExtension(new GithubFlavoredMarkdownExtension);
        $env->addExtension(new HeadingPermalinkExtension);
        $env->addExtension(new GithubAlertExtension);
        $this->converter = new MarkdownConverter($env);
    }

    public function types(): array
    {
        return (array) config('hondabase.types', []);
    }

    public function categoryExists(string $type, string $category): bool
    {
        return $this->safe($type, $category) && is_dir("{$this->root}/{$type}/{$category}");
    }

    /** Categories under a type, each with an article count. */
    public function categories(string $type): array
    {
        $dir = "{$this->root}/{$type}";
        if (! $this->safe($type) || ! is_dir($dir)) {
            return [];
        }
        $out = [];
        foreach (glob("{$dir}/*", GLOB_ONLYDIR) as $d) {
            $slug = basename($d);
            $out[] = [
                'slug' => $slug,
                'label' => $this->humanize($slug),
                'count' => count($this->articlesIn($type, $slug)),
            ];
        }
        usort($out, fn ($a, $b) => strcasecmp($a['label'], $b['label']));

        return $out;
    }

    /** Article stubs (slug + title) in a category. */
    public function articlesIn(string $type, string $category): array
    {
        $dir = "{$this->root}/{$type}/{$category}";
        if (! $this->safe($type, $category) || ! is_dir($dir)) {
            return [];
        }
        $out = [];
        foreach (glob("{$dir}/*", GLOB_ONLYDIR) as $d) {
            $slug = basename($d);
            $file = $this->mdFile($d, $slug);
            if ($file === null) {
                continue;
            }
            [$fm, $body] = $this->splitFrontMatter((string) file_get_contents($file));
            $out[] = [
                'slug' => $slug,
                'title' => $fm['title'] ?? $this->firstH1($body) ?? $this->humanize($slug),
            ];
        }
        usort($out, fn ($a, $b) => strcasecmp($a['title'], $b['title']));

        return $out;
    }

    /** Full rendered article, or null if not found. */
    public function find(string $type, string $category, string $slug): ?array
    {
        if (! $this->safe($type, $category, $slug)) {
            return null;
        }
        $dir = "{$this->root}/{$type}/{$category}/{$slug}";
        $file = $this->mdFile($dir, $slug);
        if ($file === null) {
            return null;
        }

        [$fm, $body] = $this->splitFrontMatter((string) file_get_contents($file));
        $title = $fm['title'] ?? $this->firstH1($body) ?? $this->humanize($slug);
        $body = $this->stripFirstH1($body);

        $assetBase = "/{$type}/{$category}/{$slug}";
        $html = $this->renderBody($body, $assetBase);
        $rel = "{$type}/{$category}/{$slug}/".basename($file);

        $attachments = [];
        $image_extensions = ['jpg', 'jpeg', 'png', 'gif', 'svg', 'webp'];
        $md_extensions = ['md', 'mdx', 'gitkeep'];
        foreach (glob("{$dir}/*") as $file_path) {
            if (! is_file($file_path)) {
                continue;
            }
            $filename = basename($file_path);
            if (str_starts_with($filename, '.')) {
                continue;
            }
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            if (in_array($ext, $image_extensions) || in_array($ext, $md_extensions)) {
                continue;
            }
            $attachments[] = [
                'name' => $filename,
                'url' => "/{$type}/{$category}/{$slug}/{$filename}",
                'size' => filesize($file_path),
                'hash' => hash_file('sha256', $file_path),
                'ext' => strtoupper($ext),
            ];
        }
        usort($attachments, fn ($a, $b) => strcasecmp($a['name'], $b['name']));

        return [
            'type' => $type,
            'category' => $category,
            'slug' => $slug,
            'title' => $title,
            'summary' => $fm['summary'] ?? null,
            'seo_description' => $this->seoDescription($fm['summary'] ?? null, $html, $title),
            'html' => $html,
            'type_label' => $this->humanize($type),
            'category_label' => $this->humanize($category),
            'updated' => $this->lastUpdated($rel),
            'tags' => $this->asList($fm['tags'] ?? []),
            'complexity' => $fm['complexity'] ?? null,
            'applies_to' => is_array($fm['applies_to'] ?? null) ? $fm['applies_to'] : null,
            'meta' => $fm,
            'sources' => $this->sources($fm['sources'] ?? []),
            'authors' => $this->authors->forArticle($rel),
            'edit_url' => 'https://github.com/'.config('hondabase.content_repo').'/blob/main/'.$rel,
            'attachments' => $attachments,
            'repo_path' => $rel,
        ];
    }

    /**
     * Raw on-disk markdown for an article (the whole file: frontmatter + body), plus the
     * repo-relative path, the current content HEAD sha, and the resolved title. Drives the
     * editor: the textarea holds exactly this, so an approved edit round-trips byte-for-byte.
     * Returns null if the article does not exist.
     */
    public function rawMarkdown(string $type, string $category, string $slug): ?array
    {
        if (! $this->safe($type, $category, $slug)) {
            return null;
        }
        $dir = "{$this->root}/{$type}/{$category}/{$slug}";
        $file = $this->mdFile($dir, $slug);
        if ($file === null) {
            return null;
        }
        $raw = (string) file_get_contents($file);
        [$fm, $body] = $this->splitFrontMatter($raw);

        return [
            'repo_path' => "{$type}/{$category}/{$slug}/".basename($file),
            'content' => $raw,
            'sha' => $this->headSha(),
            'title' => $fm['title'] ?? $this->firstH1($body) ?? $this->humanize($slug),
        ];
    }

    /**
     * Render a full raw markdown document (frontmatter + body, as the editor textarea holds
     * it) to ['title','html'] using the exact same pipeline as a published article. Drives
     * the editor's live preview so what the editor sees is what review/publish will show.
     */
    public function preview(string $raw, string $type, string $category, string $slug): array
    {
        [$fm, $body] = $this->splitFrontMatter($raw);
        $title = $fm['title'] ?? $this->firstH1($body) ?? $this->humanize($slug);
        $body = $this->stripFirstH1($body);
        $assetBase = $this->safe($type, $category, $slug) ? "/{$type}/{$category}/{$slug}" : '';

        return [
            'title' => $title,
            'html' => $this->renderBody($body, $assetBase),
        ];
    }

    /** Absolute path of a co-located asset, or null. */
    public function assetPath(string $type, string $category, string $slug, string $file): ?string
    {
        $file = basename($file);
        if (! $this->safe($type, $category, $slug) || $file === '' || str_contains($file, '..')) {
            return null;
        }
        $path = "{$this->root}/{$type}/{$category}/{$slug}/{$file}";

        return is_file($path) ? $path : null;
    }

    /** Lightweight scan of every article for indexing (no HTML render). */
    public function scan(): array
    {
        $rows = [];
        foreach ($this->types() as $type) {
            $tdir = "{$this->root}/{$type}";
            if (! is_dir($tdir)) {
                continue;
            }
            foreach (glob("{$tdir}/*", GLOB_ONLYDIR) as $cdir) {
                $category = basename($cdir);
                foreach (glob("{$cdir}/*", GLOB_ONLYDIR) as $adir) {
                    $row = $this->scanRow($type, $category, $adir);
                    if ($row !== null) {
                        $rows[] = $row;
                    }
                }
            }
        }

        return $rows;
    }

    /** Index row for a single article (same shape as scan()), or null if missing. */
    public function scanOne(string $type, string $category, string $slug): ?array
    {
        if (! $this->safe($type, $category, $slug)) {
            return null;
        }
        $adir = "{$this->root}/{$type}/{$category}/{$slug}";

        return is_dir($adir) ? $this->scanRow($type, $category, $adir) : null;
    }

    /** Build one index row from an article directory. */
    private function scanRow(string $type, string $category, string $adir): ?array
    {
        $slug = basename($adir);
        $file = $this->mdFile($adir, $slug);
        if ($file === null) {
            return null;
        }
        [$fm, $body] = $this->splitFrontMatter((string) file_get_contents($file));
        $rel = "{$type}/{$category}/{$slug}/".basename($file);

        return [
            'type' => $type,
            'category' => $category,
            'slug' => $slug,
            'title' => $fm['title'] ?? $this->firstH1($body) ?? $this->humanize($slug),
            'summary' => $fm['summary'] ?? null,
            'complexity' => $fm['complexity'] ?? null,
            'body_text' => trim($this->markdown->normalize($this->stripFirstH1($body))),
            'repo_path' => $rel,
            'updated' => $this->lastUpdated($rel),
            'facets' => $this->facetsFor($fm, $type, $category),
        ];
    }

    /**
     * Derive [ [kind, value, label], ... ] facets from frontmatter + path. Flexible: type,
     * category, every tag, and every applies_to field (engine families, OBD, chassis, ...).
     */
    public function facetsFor(array $fm, string $type, string $category): array
    {
        $f = [
            ['type', $type, $this->humanize($type)],
            ['category', $category, $this->humanize($category)],
        ];
        foreach ($this->asList($fm['tags'] ?? []) as $t) {
            $f[] = ['tag', Str::slug($t) ?: $t, $t];
        }
        $at = is_array($fm['applies_to'] ?? null) ? $fm['applies_to'] : [];
        $kindMap = ['engines' => 'engine', 'ecus' => 'ecu', 'models' => 'model', 'trims' => 'trim', 'systems' => 'system', 'years' => 'year'];
        foreach ($at as $key => $vals) {
            $kind = $kindMap[$key] ?? (string) $key;
            foreach ((array) $vals as $v) {
                if (! is_scalar($v) || trim((string) $v) === '') {
                    continue;
                }
                $v = trim((string) $v);
                if ($kind === 'obd') {
                    $f[] = ['obd', $v, 'OBD'.$v];
                } elseif ($kind === 'engine' && ! preg_match('/\d/', $v)) {
                    $lbl = strtoupper(preg_replace('/[-_ ]?series$/i', '', strtolower($v))).'-Series';
                    $f[] = ['engine', Str::slug($lbl), $lbl];
                } elseif ($kind === 'scope') {
                    $f[] = ['scope', Str::slug($v), ucwords(str_replace('-', ' ', $v))];
                } else {
                    $f[] = [$kind, Str::slug($v) ?: strtolower($v), $kind === 'brand' ? ucfirst($v) : $v];
                }
            }
        }
        $seen = [];
        $out = [];
        foreach ($f as $row) {
            $k = $row[0].'|'.$row[1];
            if (! isset($seen[$k])) {
                $seen[$k] = true;
                $out[] = $row;
            }
        }

        return $out;
    }

    // ----- helpers -----

    /**
     * Body markdown (no frontmatter, first H1 already stripped) -> safe HTML. Resolves
     * `::: widget <name> attrs :::` directives by swapping each for a text token, rendering
     * Markdown, then substituting the widget HTML (kept out of CommonMark so it is not
     * escaped); unknown widgets are left untouched. Shared by published articles and the
     * editor preview so both render identically.
     */
    private function renderBody(string $body, string $assetBase): string
    {
        $body = $this->expandPartials($body);
        $body = $this->markdown->normalize($body);

        $widgets = [];
        $body = preg_replace_callback(
            '/^:::[ \t]*widget[ \t]+([\w-]+)[ \t]*(.*?)[ \t]*:::[ \t]*$/m',
            function ($m) use (&$widgets) {
                $html = app(WidgetRenderer::class)->render($m[1], $this->parseAttrs($m[2]));
                if ($html === null) {
                    return $m[0];
                }
                $tok = 'xWIDGET'.count($widgets).'x';
                $widgets[$tok] = $html;

                return "\n\n{$tok}\n\n";
            },
            $body
        );

        $html = $this->converter->convert($body)->getContent();
        foreach ($widgets as $tok => $whtml) {
            $html = str_replace(["<p>{$tok}</p>", $tok], $whtml, $html);
        }
        if ($assetBase !== '') {
            $html = $this->rewriteAssets($html, $assetBase);
            $html = $this->rewriteArticleLinks($html, $assetBase);
            $html = $this->rewriteAttachmentLinks($html, $assetBase);
        }

        return $html;
    }

    /**
     * Expand `{{> name }}` partial includes from content/_partials/<name>.md, inline before
     * Markdown is parsed so shared prose renders identically wherever it is included (and may
     * itself contain widgets or links). Names are path-safe ([\w-]+); a missing partial is left
     * verbatim (like an unknown widget). Nested includes are expanded up to a small depth so a
     * partial that references itself cannot loop forever.
     */
    private function expandPartials(string $body, int $depth = 0): string
    {
        if ($depth > 4 || ! str_contains($body, '{{>')) {
            return $body;
        }

        return preg_replace_callback('/\{\{>\s*([\w-]+)\s*\}\}/', function ($m) use ($depth) {
            $file = "{$this->root}/_partials/{$m[1]}.md";
            if (! $this->safe($m[1]) || ! is_file($file)) {
                return $m[0];
            }

            return $this->expandPartials((string) file_get_contents($file), $depth + 1);
        }, $body);
    }

    /**
     * Rewrite relative `.md` links between article bundles to their clean article route.
     * Markdown links are relative to the article's own folder, so `../map-sensor/map-sensor.md`
     * from /cars/electronics/tps-sensor resolves to /cars/electronics/map-sensor. Any fragment
     * is preserved; links that don't resolve to a known <type>/<category>/<slug> are left as-is.
     */
    private function rewriteArticleLinks(string $html, string $assetBase): string
    {
        $baseDir = trim($assetBase, '/'); // e.g. cars/electronics/tps-sensor

        return preg_replace_callback('/<a\b[^>]*\bhref="([^"]+)"/i', function ($m) use ($baseDir) {
            $url = $m[1];
            if (
                preg_match('#^(?:[a-z][a-z0-9+.-]*:|//)#i', $url)
                || str_starts_with($url, '/')
                || str_starts_with($url, '#')
            ) {
                return $m[0];
            }

            [$path, $frag] = array_pad(explode('#', $url, 2), 2, null);
            $path = preg_replace('/\?.*$/', '', (string) $path);
            if (! str_ends_with(strtolower($path), '.md')) {
                return $m[0];
            }

            $resolved = $this->resolvePath($baseDir, $path);
            // Only rewrite when it lands on a real article bundle: type/category/slug/<file>.md.
            if (! preg_match('#^([^/]+)/([^/]+)/([^/]+)/[^/]+\.md$#i', $resolved, $p)
                || ! in_array($p[1], $this->types(), true)) {
                return $m[0];
            }

            $route = '/'.$p[1].'/'.$p[2].'/'.$p[3].($frag !== null ? '#'.$frag : '');

            return str_replace('href="'.$url.'"', 'href="'.$route.'"', $m[0]);
        }, $html);
    }

    /** Resolve a relative path against a base directory, collapsing `.` and `..` segments. */
    private function resolvePath(string $baseDir, string $rel): string
    {
        $segments = $baseDir === '' ? [] : explode('/', $baseDir);
        foreach (explode('/', $rel) as $seg) {
            if ($seg === '' || $seg === '.') {
                continue;
            }
            if ($seg === '..') {
                array_pop($segments);
            } else {
                $segments[] = $seg;
            }
        }

        return implode('/', $segments);
    }

    /** Whether an article bundle already exists on disk at this location. */
    public function exists(string $type, string $category, string $slug): bool
    {
        return $this->rawMarkdown($type, $category, $slug) !== null;
    }

    /** Public accessor for the current content HEAD sha (new-article revisions record it). */
    public function currentSha(): ?string
    {
        return $this->headSha();
    }

    /** Current HEAD commit sha of the content repo (for edit conflict awareness), or null. */
    private function headSha(): ?string
    {
        $cmd = 'git -C '.escapeshellarg($this->root).' rev-parse HEAD 2>/dev/null';
        $out = trim((string) @shell_exec($cmd));

        return $out !== '' ? $out : null;
    }

    private function mdFile(string $dir, string $slug): ?string
    {
        foreach (["{$dir}/{$slug}.md", "{$dir}/index.md"] as $cand) {
            if (is_file($cand)) {
                return $cand;
            }
        }
        $md = glob("{$dir}/*.md");

        return $md[0] ?? null;
    }

    /** Returns [frontmatterArray, bodyWithoutFrontmatter]. */
    private function splitFrontMatter(string $raw): array
    {
        if (! preg_match('/^---\s*?\r?\n(.*?)\r?\n---\s*?\r?\n(.*)$/s', $raw, $m)) {
            return [[], $raw];
        }
        try {
            $fm = Yaml::parse($m[1]);
        } catch (\Throwable $e) {
            $fm = [];
        }

        return [is_array($fm) ? $fm : [], $m[2]];
    }

    private function firstH1(string $md): ?string
    {
        return preg_match('/^\#\s+(.+?)\s*$/m', $md, $m) ? trim($m[1]) : null;
    }

    private function stripFirstH1(string $md): string
    {
        return preg_replace('/^\#\s+.+?\s*$\n?/m', '', $md, 1);
    }

    private function parseAttrs(string $s): array
    {
        $attrs = [];
        if (preg_match_all('/([\w-]+)="([^"]*)"/', $s, $mm, PREG_SET_ORDER)) {
            foreach ($mm as $a) {
                $attrs[$a[1]] = $a[2];
            }
        }

        return $attrs;
    }

    /** Point relative <img src> at the co-located asset route. */
    private function rewriteAssets(string $html, string $assetBase): string
    {
        return preg_replace_callback('/<img\b[^>]*\bsrc="([^"]+)"/i', function ($m) use ($assetBase) {
            $url = $m[1];
            if (preg_match('#^(https?:)?//#i', $url) || str_starts_with($url, '/') || str_starts_with($url, 'data:')) {
                return $m[0];
            }
            $new = $assetBase.'/'.ltrim(preg_replace('#^\./#', '', $url), '/');

            return str_replace('src="'.$url.'"', 'src="'.$new.'"', $m[0]);
        }, $html);
    }

    /** Point relative attachment links at the co-located asset route. */
    private function rewriteAttachmentLinks(string $html, string $assetBase): string
    {
        return preg_replace_callback('/<a\b[^>]*\bhref="([^"]+)"/i', function ($m) use ($assetBase) {
            $url = $m[1];
            if (
                preg_match('#^(?:[a-z][a-z0-9+.-]*:|//)#i', $url)
                || str_starts_with($url, '/')
                || str_starts_with($url, '#')
            ) {
                return $m[0];
            }

            $path = preg_replace('/[?#].*$/', '', $url);
            $path = ltrim(preg_replace('#^\./#', '', $path), '/');
            if (str_contains($path, '/') || ! preg_match('/\.[A-Za-z0-9]+$/', $path)) {
                return $m[0];
            }

            $suffix = substr($url, strlen(preg_replace('/[?#].*$/', '', $url)));
            $new = $assetBase.'/'.$path.$suffix;

            return str_replace('href="'.$url.'"', 'href="'.$new.'"', $m[0]);
        }, $html);
    }

    private function lastUpdated(string $relPath): ?string
    {
        $cmd = 'git -C '.escapeshellarg($this->root).' log -1 --format=%cI -- '.escapeshellarg($relPath).' 2>/dev/null';
        $out = trim((string) @shell_exec($cmd));

        return $out !== '' ? $out : null;
    }

    private function asList(mixed $v): array
    {
        if (is_array($v)) {
            return array_values(array_filter(array_map('strval', $v), fn ($s) => $s !== ''));
        }

        return is_string($v) && $v !== '' ? [$v] : [];
    }

    private function sources(mixed $sources): array
    {
        if (! is_array($sources)) {
            return [];
        }

        return array_values(array_filter(
            $sources,
            fn ($source) => is_array($source)
                && is_string($source['name'] ?? null)
                && is_string($source['url'] ?? null),
        ));
    }

    private function humanize(string $slug): string
    {
        return ucwords(str_replace(['-', '_'], ' ', $slug));
    }

    private function seoDescription(mixed $summary, string $html, string $title): string
    {
        $text = is_scalar($summary) ? trim((string) $summary) : '';
        if ($text === '') {
            $text = trim(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }
        if ($text === '') {
            $text = "{$title} technical reference from Hondabase.";
        }

        $text = preg_replace('/\s+/u', ' ', $text);

        return Str::limit($text, 157, '...');
    }

    private function safe(string ...$segments): bool
    {
        foreach ($segments as $s) {
            if ($s === '' || str_contains($s, '..') || ! preg_match('/^[A-Za-z0-9._-]+$/', $s)) {
                return false;
            }
        }

        return true;
    }
}
