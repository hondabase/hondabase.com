---
name: create-article
description: >-
  Guide and workflow for creating, drafting, and formatting technical articles in Hondabase.
  Use this skill whenever creating new articles, creating article drafts, or updating article frontmatter.
---

# Creating Articles for Hondabase

This skill defines the complete standard operating procedure for authoring, drafting, and validating technical Honda and Acura knowledge base articles for Hondabase.

---

## 1. Metadata (YAML Frontmatter) Standard

Every Hondabase article must begin with valid YAML frontmatter. Frontmatter fields are composed and formatted using `App\Support\ArticleDocument::compose($fm, $body)`.

### Mandatory & Optional Fields

```yaml
---
title: "Honda D, B, and H-Series Distributor Trigger Decoding (24+1, CYP/TDC/CKP)"
summary: "Technical breakdown of Honda 3-VR sensor distributor triggers (24+1, 4+1, 1-tooth CYP), VR polarity mapping, timing drift fixes, and standalone ECU configuration."
tags: [trigger, distributor, obd1, obd2, vr-sensor, timing, standalone]
applies_to:
  models: [civic, integra, accord, prelude]
  chassis: [eg, ek, dc2, cb7, bb]
  engines: [d-series, b-series, h-series]
complexity: advanced
sources:
  - url: "https://rusefi.com/forum/viewtopic.php?t=2013"
    title: "Honda D/B/H trigger patterns"
    author: "blundar"
    adapted: true
---
```

### Sources & Provenance (`sources`)
- **`adapted: true`**: Set when the article content was restructured, imported, or rewritten from a specific forum thread, wiki page, or community post. Displays an "Adapted from..." header badge and footer credit.
- **`adapted: false` (or omitted)**: Set when a source is cited purely as a background reference, datasheet, or further reading link.

### Key Formatting Rules for `applies_to`

> [!IMPORTANT]
> `applies_to` MUST be a structured YAML map containing specific vehicle taxonomy keys. **Never** format `applies_to` as a flat list of URL paths or strings (e.g. `['cars/honda/civic/eg']` is INVALID).

Valid sub-keys under `applies_to`:
- `models`: Array of model slugs (e.g. `[civic, integra, accord, prelude, crx, del-sol, s2000, nsx, rsx, beat, acty, element, cr-v]`)
- `chassis`: Array of chassis generation codes (e.g. `[ef, eg, ek, da, dc2, dc5, cb7, cd5, cl9, bb, pp1, ha4, rd1]`)
- `engines`: Array of engine families or engine codes (e.g. `[d-series, b-series, k-series, h-series, f-series, e07a, d15, d16, b16, b18, b20, k20, k24, f20, f22, f23, h22]`)
- `ecus`: Array of ECU hardware families (e.g. `[obd1-p28, obd1-p30, obd1-p72, obd2a-p72, obd2b-pr3]`) *(optional)*
- `trims`: Array of trim designations (e.g. `[si, type-r, gsr, vti]`) *(optional)*

### OBD Tags Rule
- **OBD terms belong in `tags`, NOT in `applies_to`.** Put terms like `obd0`, `obd1`, `obd2a`, `obd2b` under `tags: [...]`.

---

## 2. Technical Body Formatting Rules

1. **No Em Dashes:** Never use em dashes (`—`) or en dashes (`–`) anywhere in titles, headers, prose, notes, or comments. Use plain hyphens (`-`), commas, colons, or parentheses.
2. **Single `#` Header:** Use a single top-level `#` title matching the frontmatter `title`.
3. **No Duplicate Section Headers:** Each section heading must be unique. Never duplicate section headers.
4. **GFM Callout Alerts (Unescaped):** Use standard unescaped GFM alert blocks for notes and warnings:
   - `> [!NOTE]` — General technical context.
   - `> [!TIP]` — Helpful suggestions.
   - `> [!IMPORTANT]` — Essential technical details.
   - `> [!WARNING]` — Safety or damage precautions.
   *(Never escape callout brackets with backslashes like `\[!TIP\]`.)*
5. **Image Captions Line Breaks:** Always put a newline between an image tag and its italicized caption:
   ```markdown
   ![Descriptive Alt Text](image_name.png)
   *Italicized caption text describing the photo or schematic.*
   ```
6. **Tables:** Present pinout maps, wire colors, electrical specs, and scaling factors using markdown tables.
7. **Carousels:** Use ````carousel` blocks for multi-slide images (PCB front/back, harness views).

---

## 3. Image Watermarking & Author Attribution

1. **Author Watermark Overlay:** All community photos, schematics, or illustrations imported from external threads (rusEFI, 4GUK, PGMFI, Honda-Tech) MUST be watermarked with an author attribution overlay (`Photo: {author} • Hondabase Archive`) before publication.
2. **Caption Credit:** Captions must explicitly credit the original thread poster or photographer:
   `*D-series coil-on-plug conversion bracket (photo: rtmickelwait).*`
3. **Mandatory Asset Embedding:** Every image or attachment listed in `$draft->assets` MUST be explicitly embedded in the Markdown body:
   - Images/Schematics: `![Alt Text](filename.jpg)` or inside a ````carousel` block.
   - PDFs/Firmware Files: `[Schematic Datasheet PDF](filename.pdf)` download link.

---

## 4. Creating Draft Articles in Code

When instantiating draft articles for a user (`App\Models\ArticleDraft`):

```php
use App\Models\ArticleDraft;
use App\Models\User;
use App\Support\ArticleDocument;

$fm = [
    'title' => 'Article Title Here',
    'summary' => '1-2 sentence high-intent summary.',
    'tags' => ['tag1', 'tag2'],
    'applies_to' => [
        'models' => ['civic', 'integra'],
        'chassis' => ['eg', 'dc2'],
        'engines' => ['b-series', 'd-series']
    ],
    'complexity' => 'intermediate',
    'sources' => [
        ['url' => 'https://...', 'title' => 'Source Title', 'author' => 'AuthorName', 'adapted' => true]
    ]
];

$document = ArticleDocument::compose($fm, $markdownBody);

$draft = ArticleDraft::create([
    'user_id' => $userId,
    'title' => $fm['title'],
    'type' => 'cars',
    'category' => 'electronics',
    'slug' => 'article-slug-here',
    'document' => $document,
    'note' => 'Draft description note.',
    'assets' => ['image1.png', 'diagram.jpg']
]);

// Copy asset files into the draft's private directory
$assetDir = $draft->assetDirectory(); // storage/app/draft-assets/{draft_id}
if (!is_dir($assetDir)) {
    mkdir($assetDir, 0755, true);
}
// Copy images to $assetDir...
```

---

## 5. Verification & Testing Workflow

After creating or updating articles/drafts:

1. **Lint Articles:** Run `php artisan app:lint-articles` to check frontmatter and Markdown structure across content.
2. **Editor Round-Trip Test:** Run `php scripts/editor-roundtrip.php` to ensure TipTap editor HTML/Markdown conversion remains 100% lossless.
3. **Reindex (Derived Index):** Run `php artisan hondabase:reindex` to test MariaDB taxonomy compatibility resolution.
4. **Code Style:** Run `vendor/bin/pint` to fix PHP formatting.
5. **Permissions:** Restore file ownership: `chown -R www-data:www-data /var/www/hondabase/www/storage /var/www/hondabase/www/content`.
