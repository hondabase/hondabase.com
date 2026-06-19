# Article Formatting Standards

This document defines the mandatory formatting and stylistic conventions for all technical articles in this project. All new or edited content must adhere to these standards to ensure consistency, scannability, and search-engine optimization (SEO).

## 1. Metadata (YAML Frontmatter)
Every article must start with a YAML frontmatter block containing the following fields:

- `summary`: A concise, high-intent summary (1–2 sentences) suitable for search engine results.
- `tags`: A list of relevant, searchable keywords (e.g., `[ecu, wiring, tuning, diagnostics]`). Use OBD terms here (`obd`, `obd0`, `obd1`, `obd2`, `obd2a`, `obd2b`) only when the article is specifically about that OBD topic.
- `applies_to`: YAML object defining concrete applicability (e.g., `models`, `chassis`, `engines`, `ecus`). Do not use `obd` under `applies_to`; OBD is a tag.
- `complexity`: `beginner`, `intermediate`, or `advanced`.

## 2. Structure and Hierarchy
- **Title:** Use a single `#` header. It must be descriptive and keyword-rich for SEO.
- **Header Hierarchy:** Use `##` for primary sections and `###` for sub-sections.
- **Consistency:** Use clear, scannable sectioning. Avoid dense, unbroken walls of text.

## 3. Scannability
- **Bullet Points:** Use for lists of parts, procedures, or features.
- **Technical Tables:** Use Markdown tables to present pinouts, scaling factors, or comparison data.
- **Bold Labels:** Bold key labels (e.g., **Note:**, **Warning:**, **Pin A1:**) to act as visual anchors.
- **GFM Alerts:** Use blockquotes with standard GitHub alert syntax for callouts. Prefer these over plain bold labels for high-visibility notes:
  - `> [!NOTE]` — General information.
  - `> [!TIP]` — Helpful suggestions.
  - `> [!IMPORTANT]` — Essential technical details.
  - `> [!WARNING]` — Critical precautions.
  - `> [!CAUTION]` — Potential for damage.

## 4. Advanced Components
Utilize these custom interactive components where appropriate to improve technical clarity:

### 4.1 Image Carousels
Use for multiple images of the same component (e.g., front/back of a PCB).
```carousel
![Front view](front.jpg)
*Front of PCB showing main components*
<!-- slide -->
![Back view](back.jpg)
*Back of PCB showing solder points*
```
- **Constraint:** Must contain at least two slides. Each slide requires a local image with alt text and an optional italicized caption.

### 4.2 ECU Wirelists
Use for searchable, structured ECU pinouts or component trace tables.
```wirelist
{
  "title": "ECU Component Trace",
  "variants": [
    {
      "id": "p30",
      "label": "P30 (OBD1)",
      "groups": [
        {
          "label": "MCU to Latch",
          "rows": [
            { "pin": "MCU Pin 1", "signal": "AD0", "path": "MCU Pin 1 -> Latch Pin 3", "note": "Data bus bit 0" }
          ]
        }
      ]
    }
  ]
}
```

### 4.3 Widgets
Embed interactive system tools using the widget directive. Available widgets:
- `::: widget error-codes :::` — Searchable OBD error code database.
- `::: widget wideband-wiring-table :::` — Wideband sensor wiring reference.

### 4.4 Partial Includes
Reuse common technical blocks using partials (found in `_partials/` folder).
`{{> name }}` — e.g., `{{> resistor-color-codes }}`.

## 5. Tone and Content Quality
- **Agnostic/Clean:** Remove all references to external community project branding (e.g., "pgmfi.org"), wiki-specific conversational meta-notes (e.g., "Describe here", "Editor's note"), and personal developer notes.
- **Direct Tone:** Use professional, actionable technical language. Avoid conversational filler or introductory fluff.
- **Technical Accuracy:** Ensure technical specifications (e.g., wire colors, resistor values) are clearly formatted and easy to cross-reference.
- **SEO-Friendly:** Focus on descriptive titles and clear categorization, avoiding ambiguous or "internal-only" naming conventions.
