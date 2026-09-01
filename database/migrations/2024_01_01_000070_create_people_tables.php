<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('login_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('logged_in_at')->index();
            $table->string('ip_address', 45)->nullable()->index();
            $table->text('user_agent')->nullable();
            $table->string('device', 255)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'logged_in_at']);
        });

        Schema::create('attendance_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('local_date')->index();
            $table->string('status', 32)->default('present')->index(); // present|leave|holiday|absent
            $table->timestamp('first_login_at')->nullable();
            $table->boolean('is_late')->default(false)->index();
            $table->string('source', 32)->default('login')->index(); // login|leave|holiday|sync
            $table->text('notes')->nullable();
            $table->foreignId('marked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'local_date']);
            $table->index(['local_date', 'status']);
        });

        Schema::create('work_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('local_date')->index();
            $table->text('body');
            $table->json('task_ids')->nullable();
            $table->json('article_ids')->nullable();
            $table->json('link_ids')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'local_date']);
            $table->index(['user_id', 'local_date']);
        });

        Schema::create('pay_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 32)->index(); // monthly_salary|per_article|per_link|per_task
            $table->unsignedBigInteger('amount_paisa');
            $table->string('currency', 3)->default('PKR');
            $table->boolean('is_active')->default(true)->index();
            $table->date('effective_from')->nullable()->index();
            $table->date('effective_to')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'is_active']);
            $table->index(['user_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pay_rates');
        Schema::dropIfExists('work_logs');
        Schema::dropIfExists('attendance_days');
        Schema::dropIfExists('login_histories');
    }
};
