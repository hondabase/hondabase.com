<?php

namespace App\Console\Commands;

use App\Markdown\MarkdownNormalizer;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Symfony\Component\Yaml\Yaml;

#[Signature('app:lint-articles')]
#[Description('Lints Hondabase articles for structure, frontmatter validity, and co-located assets')]
class LintArticles extends Command
{
    /**
     * Execute the console command.
     */
    public function handle()
    {
        $contentPath = config('hondabase.content_path', base_path('content'));
        $types = config('hondabase.types', ['cars', 'motorcycles', 'aircraft', 'common']);

        if (! is_dir($contentPath)) {
            $this->error("Content directory does not exist: {$contentPath}");

            return 1;
        }

        $this->info("Scanning articles in: {$contentPath}");

        $errors = [];
        $warnings = [];
        $checkedCount = 0;
        $normalizer = app(MarkdownNormalizer::class);

        foreach ($types as $type) {
            $typePath = "{$contentPath}/{$type}";
            if (! is_dir($typePath)) {
                continue;
            }

            foreach (glob("{$typePath}/*", GLOB_ONLYDIR) as $categoryDir) {
                $category = basename($categoryDir);

                foreach (glob("{$categoryDir}/*", GLOB_ONLYDIR) as $articleDir) {
                    $slug = basename($articleDir);
                    $checkedCount++;

                    // 1. Structural Checks
                    $expectedFiles = ["{$articleDir}/{$slug}.md", "{$articleDir}/index.md"];
                    $mainFile = null;
                    foreach ($expectedFiles as $expectedFile) {
                        if (is_file($expectedFile)) {
                            $mainFile = $expectedFile;
                            break;
                        }
                    }

                    if ($mainFile === null) {
                        $allMds = glob("{$articleDir}/*.md");
                        if (! empty($allMds)) {
                            $mainFile = $allMds[0];
                            $warnings[] = "[{$type}/{$category}/{$slug}] Main markdown file should be named '{$slug}.md' or 'index.md', found '".basename($mainFile)."'";
                        } else {
                            $errors[] = "[{$type}/{$category}/{$slug}] Missing markdown file. Expected '{$slug}.md' or 'index.md'";

                            continue;
                        }
                    }

                    // Check for multiple markdown files
                    $mdFiles = glob("{$articleDir}/*.md");
                    if (count($mdFiles) > 1) {
                        $warnings[] = "[{$type}/{$category}/{$slug}] Multiple markdown files found: ".implode(', ', array_map('basename', $mdFiles));
                    }

                    // Read content
                    $content = file_get_contents($mainFile);

                    // 2. Frontmatter Checks
                    $fm = [];
                    $body = $content;
                    if (preg_match('/^---\s*?\r?\n(.*?)\r?\n---\s*?\r?\n(.*)$/s', $content, $m)) {
                        $fmRaw = $m[1];
                        $body = $m[2];
                        try {
                            $fm = Yaml::parse($fmRaw);
                            if (! is_array($fm)) {
                                $errors[] = "[{$type}/{$category}/{$slug}] Frontmatter must be a YAML object/array";
                                $fm = [];
                            }
                        } catch (\Throwable $e) {
                            $errors[] = "[{$type}/{$category}/{$slug}] Failed to parse frontmatter YAML: ".$e->getMessage();
                            $fm = [];
                        }
                    }

                    if (! empty($fm)) {
                        // Check for allowed keys
                        $allowedKeys = ['title', 'summary', 'tags', 'applies_to', 'complexity', 'sources'];
                        foreach (array_keys($fm) as $key) {
                            if (! in_array($key, $allowedKeys, true)) {
                                $errors[] = "[{$type}/{$category}/{$slug}] Disallowed frontmatter key: '{$key}'. Only ".implode(', ', $allowedKeys).' are allowed.';
                            }
                        }

                        // Check last_updated, date, category
                        if (isset($fm['last_updated'])) {
                            $errors[] = "[{$type}/{$category}/{$slug}] Frontmatter contains 'last_updated'. This must be git-derived at build time.";
                        }
                        if (isset($fm['date'])) {
                            $errors[] = "[{$type}/{$category}/{$slug}] Frontmatter contains 'date'. Date fields are not allowed.";
                        }
                        if (isset($fm['category'])) {
                            $errors[] = "[{$type}/{$category}/{$slug}] Frontmatter contains 'category'. Category is derived from folder path.";
                        }

                        // Check complexity
                        if (isset($fm['complexity'])) {
                            $validComplexity = ['beginner', 'intermediate', 'advanced'];
                            if (! in_array($fm['complexity'], $validComplexity, true)) {
                                $errors[] = "[{$type}/{$category}/{$slug}] Invalid complexity '{$fm['complexity']}'. Must be one of: ".implode(', ', $validComplexity);
                            }
                        }

                        // Check tags
                        if (isset($fm['tags'])) {
                            if (! is_array($fm['tags'])) {
                                $errors[] = "[{$type}/{$category}/{$slug}] 'tags' must be an array";
                            } else {
                                foreach ($fm['tags'] as $tag) {
                                    if (! is_string($tag)) {
                                        $errors[] = "[{$type}/{$category}/{$slug}] Tag must be a string, found: ".json_encode($tag);
                                    }
                                }
                            }
                        }

                        // Check applies_to
                        if (isset($fm['applies_to'])) {
                            if (! is_array($fm['applies_to'])) {
                                $errors[] = "[{$type}/{$category}/{$slug}] 'applies_to' must be an object/array";
                            } else {
                                $allowedAppliesToKeys = ['brand', 'models', 'model', 'chassis', 'trims', 'trim', 'engines', 'ecus', 'obd', 'years', 'scope'];
                                foreach (array_keys($fm['applies_to']) as $key) {
                                    if (! in_array($key, $allowedAppliesToKeys, true)) {
                                        $errors[] = "[{$type}/{$category}/{$slug}] Disallowed key under 'applies_to': '{$key}'. Only ".implode(', ', $allowedAppliesToKeys).' are allowed.';
                                    }
                                }
                                if (isset($fm['applies_to']['obd'])) {
                                    $obdVal = $fm['applies_to']['obd'];
                                    if (is_array($obdVal)) {
                                        foreach ($obdVal as $val) {
                                            if (! is_int($val) && ! is_string($val)) {
                                                $errors[] = "[{$type}/{$category}/{$slug}] OBD value must be int or string, found: ".json_encode($val);
                                            }
                                        }
                                    } elseif (! is_int($obdVal) && ! is_string($obdVal)) {
                                        $errors[] = "[{$type}/{$category}/{$slug}] OBD value must be int or string, found: ".json_encode($obdVal);
                                    }
                                }
                            }
                        }

                        if (isset($fm['sources'])) {
                            if (! is_array($fm['sources']) || ! array_is_list($fm['sources']) || $fm['sources'] === []) {
                                $errors[] = "[{$type}/{$category}/{$slug}] 'sources' must be a non-empty list";
                            } else {
                                foreach ($fm['sources'] as $index => $source) {
                                    if (! is_array($source)) {
                                        $errors[] = "[{$type}/{$category}/{$slug}] Source #".($index + 1).' must be an object/array';

                                        continue;
                                    }
                                    foreach (['name', 'title', 'url', 'license', 'license_url'] as $field) {
                                        if (! is_string($source[$field] ?? null) || trim($source[$field]) === '') {
                                            $errors[] = "[{$type}/{$category}/{$slug}] Source #".($index + 1)." requires a non-empty '{$field}' string";
                                        }
                                    }
                                    if (isset($source['adapted']) && ! is_bool($source['adapted'])) {
                                        $errors[] = "[{$type}/{$category}/{$slug}] Source #".($index + 1)." 'adapted' must be boolean";
                                    }
                                }
                            }
                        }
                    }

                    if (! is_string($fm['summary'] ?? null) || trim($fm['summary']) === '') {
                        $warnings[] = "[{$type}/{$category}/{$slug}] Missing a frontmatter summary for the meta description";
                    } elseif (mb_strlen(trim($fm['summary'])) > 160) {
                        $warnings[] = "[{$type}/{$category}/{$slug}] Summary is ".mb_strlen(trim($fm['summary'])).' characters; keep SEO descriptions at or below 160';
                    }

                    if ($normalizer->normalize($body) !== $body) {
                        $warnings[] = "[{$type}/{$category}/{$slug}] Contains a collapsed one-line Markdown table; split each table row onto its own line";
                    }

                    $repoPath = "{$type}/{$category}/{$slug}/".basename($mainFile);
                    $isPgmfiPort = ! in_array($repoPath, (array) config('hondabase.pgmfi_non_ports', []), true);
                    if ($type === 'cars' && $category === 'electronics' && $isPgmfiPort && empty($fm['sources'])) {
                        $errors[] = "[{$type}/{$category}/{$slug}] PGMFI-ported electronics articles require source metadata";
                    }

                    // 3. Image Reference Checks
                    if (preg_match_all('/!\[.*?\]\((.*?)\)/', $body, $imgMatches)) {
                        foreach ($imgMatches[1] as $imgUrl) {
                            // Only check relative local paths
                            if (preg_match('#^(https?:)?//#i', $imgUrl) || str_starts_with($imgUrl, '/') || str_starts_with($imgUrl, 'data:')) {
                                continue;
                            }
                            // Clean potential query params or anchors
                            $cleanImgName = preg_replace('/[?#].*$/', '', $imgUrl);
                            $cleanImgName = ltrim(preg_replace('#^\./#', '', $cleanImgName), '/');
                            $imgPath = "{$articleDir}/{$cleanImgName}";
                            if (! is_file($imgPath)) {
                                $errors[] = "[{$type}/{$category}/{$slug}] Referenced local image does not exist: {$cleanImgName}";
                            }
                        }
                    }

                }
            }
        }

        $this->line('');
        $this->info("Checked {$checkedCount} articles.");

        if (count($warnings) > 0) {
            $this->warn("\nFound ".count($warnings).' warnings:');
            foreach ($warnings as $w) {
                $this->warn("  - {$w}");
            }
        }

        if (count($errors) > 0) {
            $this->error("\nFound ".count($errors).' errors:');
            foreach ($errors as $e) {
                $this->error("  - {$e}");
            }

            return 1;
        }

        $this->info("\nAll checks passed successfully!");

        return 0;
    }
}
