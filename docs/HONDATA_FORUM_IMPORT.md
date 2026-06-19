# Hondata Forum Research Import

Use `scripts/hondata-forum-inventory.php` to build a local inventory of forum topics for editorial
triage. The default mode writes one JSON file per topic with source URL, title, small excerpts, and
conversion signals. It does not mirror full post text by default.

Credentials must be supplied through environment variables, never committed:

```bash
HONDATA_USERNAME='...' HONDATA_PASSWORD='...' \
  php scripts/hondata-forum-inventory.php --delay-ms=2000
```

Useful dry run:

```bash
HONDATA_USERNAME='...' HONDATA_PASSWORD='...' \
  php scripts/hondata-forum-inventory.php --max-pages=5 --max-topics=20
```

Output defaults to `storage/app/imports/hondata-forum/`:

- `topics/*.json` - one file per discovered topic.
- `manifest.jsonl` - compact index for review tooling.
- `run.json` - crawl settings and counts.

Only pass `--include-post-text` when you have permission to archive the forum content. Hondabase
articles should be original, sourced writeups: use the forum inventory to identify topics, facts,
edge cases, and source links, not to republish posts verbatim.
