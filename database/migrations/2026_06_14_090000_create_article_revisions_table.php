<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pending in-browser edits (the approval queue). This is instance-local community data,
 * NOT content-derived: it is the staging area between a submitted suggestion and the git
 * commit that makes it canonical. Once an edit is approved and committed, git history is
 * the authoritative record; this row keeps the review trail.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('article_revisions', function (Blueprint $table) {
            $table->id();

            // Author of the suggested edit (a guild-gated signed-in user).
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            // Target article location + a denormalized title for the queue listing.
            $table->string('type');
            $table->string('category');
            $table->string('slug');
            $table->string('title');
            $table->string('repo_path'); // e.g. cars/electronics/ecu/ecu.md

            // Conflict awareness: the content HEAD the edit was based on, plus the file as
            // it then read (for an honest diff even if main moves on before review).
            $table->string('base_sha', 64)->nullable();
            $table->longText('original_body');
            $table->longText('proposed_body');

            // Why the editor made the change (shown to the reviewer; not committed).
            $table->string('summary', 500)->nullable();

            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending')->index();

            // Review trail.
            $table->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('review_notes', 500)->nullable();
            $table->timestamp('reviewed_at')->nullable();

            // Set once the bot commits to content/. `pushed` flips true when the deploy-key
            // push to origin succeeds; unpushed-but-committed rows are the admin warning count.
            $table->string('commit_sha', 64)->nullable();
            $table->boolean('pushed')->default(false);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('article_revisions');
    }
};
