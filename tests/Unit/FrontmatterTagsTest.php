<?php

namespace Tests\Unit;

use App\Support\FrontmatterTags;
use PHPUnit\Framework\TestCase;

class FrontmatterTagsTest extends TestCase
{
    private function tmp(string $contents): string
    {
        $path = sys_get_temp_dir().'/fmtags-'.uniqid().'.md';
        file_put_contents($path, $contents);

        return $path;
    }

    public function test_removes_tag_and_preserves_others(): void
    {
        $path = $this->tmp("---\ntags: [tuning, rom, ecu, memory]\ncomplexity: beginner\n---\n# Title\n\nBody.\n");

        $this->assertTrue(FrontmatterTags::removeTag($path, 'rom'));

        $out = file_get_contents($path);
        $this->assertStringContainsString('tags: [tuning, ecu, memory]', $out);
        $this->assertStringContainsString('complexity: beginner', $out);
        $this->assertStringContainsString('# Title', $out);
        $this->assertDoesNotMatchRegularExpression('/\brom\b/', $out);
        unlink($path);
    }

    public function test_returns_false_and_leaves_file_unchanged_when_tag_absent(): void
    {
        $original = "---\ntags: [tuning, ecu]\n---\n# Title\n";
        $path = $this->tmp($original);

        $this->assertFalse(FrontmatterTags::removeTag($path, 'rom'));
        $this->assertSame($original, file_get_contents($path));
        unlink($path);
    }
}
