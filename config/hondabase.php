<?php

return [
    // Local working clone of hondabase/articles (canonical content repo).
    'content_path' => env('HONDABASE_CONTENT_PATH', base_path('content')),

    // Local archive used to recover original PGMFI wiki authors and source URLs.
    'pgmfi_source_path' => env('HONDABASE_PGMFI_SOURCE_PATH', base_path('tools/wiki-import/source/twiki')),

    // Electronics articles added independently rather than adapted from the PGMFI archive.
    'pgmfi_non_ports' => [
        'cars/electronics/how-to-wire-wideband/how-to-wire-wideband.md',
        'cars/electronics/iacv-circuit-fix-r58-r59/iacv-circuit-fix-r58-r59.md',
    ],

    // Vehicle types = top-level content folders.
    'types' => ['cars', 'motorcycles', 'aircraft', 'common'],

    'content_repo' => 'hondabase/articles',

    // Google Analytics 4 measurement id (optional; forks can leave it unset).
    'ga_id' => env('GA_MEASUREMENT_ID'),

    // Git identity + attribution for approved edits committed to content/.
    // Edits are committed as the bot; the human editor is credited with Co-Authored-By
    // (their real GitHub no-reply address if they linked GitHub, else a synthetic one) and
    // the approver with Reviewed-By. Push uses the repo's configured remote/credentials
    // (deploy key via core.sshCommand); when that is absent the commit lands locally and is
    // counted as "unpushed" until a key is configured.
    'git' => [
        'bot_name'         => env('HONDABASE_GIT_BOT_NAME', 'Hondabase Bot'),
        'bot_email'        => env('HONDABASE_GIT_BOT_EMAIL', 'bot@hondabase.com'),
        'branch'           => env('HONDABASE_GIT_BRANCH', 'main'),
        'push'             => env('HONDABASE_GIT_PUSH', false), // off until a deploy key exists
        'noreply_domain'   => 'users.noreply.github.com',       // GitHub Co-Authored-By format
        'synthetic_domain' => 'discord.hondabase.com',          // for editors without GitHub
    ],
];
