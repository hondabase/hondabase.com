<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

/**
 * Nightly database backup. Plain .sql (git-diff friendly) committed alongside the site repo.
 * Transient tables are excluded; email is never collected so no email PII can be present.
 * Only runs when something changed that day (override with --force).
 */
class Dump extends Command
{
    protected $signature = 'hondabase:dump {--force : Dump even if nothing changed today}';

    protected $description = 'Back up the database to database/dumps and commit it with the site repo';

    private const SKIP_TABLES = [
        'sessions', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs', 'password_reset_tokens',
    ];

    public function handle(): int
    {
        if (! $this->option('force') && ! $this->changedToday()) {
            $this->info('No changes today; skipping dump.');

            return self::SUCCESS;
        }

        $db = config('database.connections.'.config('database.default'));
        $dir = base_path('database/dumps');
        $file = $dir.'/hondabase.sql';
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $args = [
            'mariadb-dump', '--no-tablespaces', '--single-transaction', '--skip-lock-tables',
            '--host', (string) $db['host'], '--port', (string) $db['port'],
            '--user', (string) $db['username'], '--password='.(string) $db['password'],
        ];
        foreach (self::SKIP_TABLES as $t) {
            $args[] = '--ignore-table='.$db['database'].'.'.$t;
        }
        $args[] = $db['database'];

        $handle = fopen($file, 'w');
        $process = new Process($args);
        $process->setTimeout(600);
        $process->run(function ($type, $buffer) use ($handle) {
            if ($type === Process::OUT) {
                fwrite($handle, $buffer);
            }
        });
        fclose($handle);

        if (! $process->isSuccessful()) {
            $this->error('mariadb-dump failed: '.trim($process->getErrorOutput()));

            return self::FAILURE;
        }

        $this->info('Wrote '.$file.' ('.number_format(filesize($file)).' bytes)');
        $this->commitWithSite($file);

        return self::SUCCESS;
    }

    private function changedToday(): bool
    {
        $today = Carbon::today();
        foreach ([
            'users' => 'updated_at',
            'follows' => 'created_at',
            'article_revisions' => 'updated_at',
            'article_authors' => 'updated_at',
            'articles' => 'last_viewed_at',
            'article_link_clicks' => 'updated_at',
        ] as $table => $col) {
            if (DB::table($table)->where($col, '>=', $today)->exists()) {
                return true;
            }
        }
        $content = (string) config('hondabase.content_path');
        if (is_dir($content.'/.git')) {
            $p = new Process(['git', '-C', $content, 'log', '-1', '--since=midnight', '--format=%H']);
            $p->run();
            if (trim($p->getOutput()) !== '') {
                return true;
            }
        }

        return false;
    }

    private function commitWithSite(string $file): void
    {
        $root = base_path();
        if (! is_dir($root.'/.git')) {
            $this->warn('Site repo not initialised; dump kept locally at '.$file);

            return;
        }
        foreach ([['add', $file], ['commit', '-m', 'Nightly DB backup '.date('Y-m-d')], ['push']] as $cmd) {
            (new Process(array_merge(['git', '-C', $root], $cmd)))->run();
        }
        $this->info('Committed the dump with the site repo.');
    }
}
