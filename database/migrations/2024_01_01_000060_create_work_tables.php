<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->unsignedInteger('monthly_link_target')->default(0)->after('notes');
            $table->unsignedBigInteger('monthly_link_budget_paisa')->default(0)->after('monthly_link_target');
        });

        Schema::create('task_templates', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('category', 32)->index();
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('task_template_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('type', 32)->default('ad_hoc')->index();
            $table->string('status', 32)->default('assigned')->index();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->date('due_date')->nullable()->index();
            $table->unsignedInteger('time_spent_minutes')->default(0);
            $table->text('rejection_reason')->nullable();
            $table->string('recurrence_frequency', 16)->nullable()->index();
            $table->boolean('is_recurrence_source')->default(false)->index();
            $table->foreignId('recurrence_source_id')->nullable()->constrained('tasks')->nullOnDelete();
            $table->date('next_occurrence_date')->nullable()->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable()->index();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'status']);
            $table->index(['assigned_to', 'status']);
            $table->index(['assigned_to', 'due_date']);
            $table->index(['project_id', 'task_template_id']);
        });

        Schema::create('task_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('body');
            $table->timestamps();

            $table->index(['task_id', 'created_at']);
            $table->index('user_id');
        });

        Schema::create('expense_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->boolean('is_system')->default(false);
            $table->timestamps();
        });

        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('expense_category_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('amount_paisa');
            $table->string('currency', 3)->default('PKR');
            $table->string('description');
            $table->date('expense_date')->index();
            $table->string('source_type')->nullable()->index();
            $table->unsignedBigInteger('source_id')->nullable()->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['project_id', 'expense_date']);
            $table->index(['source_type', 'source_id']);
        });

        Schema::create('articles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('target_keyword');
            $table->unsignedInteger('word_count_target')->nullable();
            $table->unsignedInteger('word_count_actual')->nullable();
            $table->foreignId('writer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 32)->default('brief')->index();
            $table->unsignedBigInteger('cost_paisa')->default(0);
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->string('published_url')->nullable();
            $table->date('publish_date')->nullable()->index();
            $table->text('revision_notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable()->index();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('expense_id')->nullable()->constrained('expenses')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['project_id', 'status']);
            $table->index(['writer_id', 'status']);
            $table->index(['project_id', 'target_keyword']);
        });

        Schema::create('links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('source_url');
            $table->string('source_domain')->index();
            $table->unsignedSmallInteger('dr')->nullable();
            $table->unsignedSmallInteger('da')->nullable();
            $table->string('target_page');
            $table->string('anchor_text');
            $table->string('type', 64)->default('guest_post')->index();
            $table->unsignedBigInteger('cost_paisa')->default(0);
            $table->date('link_date')->nullable()->index();
            $table->string('live_status', 16)->default('live')->index();
            $table->string('workflow_status', 32)->default('pending')->index();
            $table->text('rejection_reason')->nullable();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable()->index();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('expense_id')->nullable()->constrained('expenses')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['project_id', 'workflow_status']);
            $table->index(['project_id', 'source_domain']);
            $table->index(['assigned_to', 'workflow_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('links');
        Schema::dropIfExists('articles');
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('expense_categories');
        Schema::dropIfExists('task_comments');
        Schema::dropIfExists('tasks');
        Schema::dropIfExists('task_templates');

        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn(['monthly_link_target', 'monthly_link_budget_paisa']);
        });
    }
};
