<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_usage_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('feature', 64);
            $table->string('provider', 32)->nullable();
            $table->string('model', 64)->nullable();
            $table->string('report_key', 64)->nullable();
            $table->unsignedInteger('prompt_tokens')->default(0);
            $table->unsignedInteger('completion_tokens')->default(0);
            $table->unsignedInteger('total_tokens')->default(0);
            $table->unsignedInteger('estimated_cost_cents')->default(0);
            $table->boolean('cached')->default(false);
            $table->boolean('success')->default(true);
            $table->string('error_message', 500)->nullable();
            $table->json('safe_meta')->nullable();
            $table->timestamps();

            $table->index(['created_at']);
            $table->index(['user_id', 'created_at']);
            $table->index(['feature', 'created_at']);
            $table->index(['report_key']);
        });

        Schema::create('ai_draft_notes', function (Blueprint $table) {
            $table->id();
            $table->string('type', 64);
            $table->string('period', 32)->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->text('body');
            $table->json('source_figures')->nullable();
            $table->string('status', 32)->default('draft');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['type', 'period']);
            $table->index(['status', 'created_at']);
            $table->index(['project_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_draft_notes');
        Schema::dropIfExists('ai_usage_logs');
    }
};
