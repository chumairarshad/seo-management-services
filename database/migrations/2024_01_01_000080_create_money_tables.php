<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Milestone 5 — Money: revenues, expanded expenses, allocations, distributions, partner ledger.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('revenue_import_batches', function (Blueprint $table) {
            $table->id();
            $table->string('filename');
            $table->string('source', 32)->default('adsense')->index();
            $table->unsignedInteger('row_count')->default(0);
            $table->unsignedInteger('imported_count')->default(0);
            $table->unsignedInteger('skipped_count')->default(0);
            $table->string('status', 32)->default('completed')->index();
            $table->text('notes')->nullable();
            $table->foreignId('imported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('created_at');
        });

        Schema::create('revenues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->date('period_month')->index(); // first day of calendar month (UTC date)
            $table->string('source', 32)->index();
            $table->unsignedBigInteger('amount_usd_cents')->default(0);
            $table->unsignedBigInteger('fx_rate_e6')->default(0); // base currency per 1 source currency × 1e6
            $table->unsignedBigInteger('amount_pkr_paisa');
            $table->string('currency_input', 3)->default('USD');
            $table->text('notes')->nullable();
            $table->foreignId('import_batch_id')->nullable()->constrained('revenue_import_batches')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['project_id', 'period_month']);
            $table->index(['period_month', 'source']);
        });

        Schema::create('recurring_expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_shared')->default(false)->index();
            $table->foreignId('expense_category_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('amount_paisa');
            $table->string('currency', 3)->default('PKR');
            $table->string('description');
            $table->unsignedTinyInteger('day_of_month')->default(1);
            $table->date('next_run_date')->index();
            $table->date('ends_on')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamp('last_generated_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'next_run_date']);
        });

        // Expand expenses (M3 was project-only; M5 adds shared + paid + receipt + recurring link)
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropForeign(['project_id']);
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->unsignedBigInteger('project_id')->nullable()->change();
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->foreign('project_id')->references('id')->on('projects')->nullOnDelete();
            $table->boolean('is_shared')->default(false)->after('project_id')->index();
            $table->boolean('is_paid')->default(false)->after('notes')->index();
            $table->timestamp('paid_at')->nullable()->after('is_paid');
            $table->string('receipt_path')->nullable()->after('paid_at');
            $table->string('receipt_original_name')->nullable()->after('receipt_path');
            $table->foreignId('recurring_expense_id')->nullable()->after('receipt_original_name')
                ->constrained('recurring_expenses')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->after('created_by')
                ->constrained('users')->nullOnDelete();

            $table->index(['is_shared', 'expense_date']);
            $table->index(['is_paid', 'expense_date']);
        });

        Schema::create('expense_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expense_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->date('period_month')->index();
            $table->unsignedBigInteger('amount_paisa');
            $table->unsignedInteger('revenue_share_bps')->default(0); // snapshot of share used
            $table->unsignedBigInteger('project_revenue_paisa')->default(0);
            $table->unsignedBigInteger('portfolio_revenue_paisa')->default(0);
            $table->timestamps();

            $table->unique(['expense_id', 'project_id']);
            $table->index(['project_id', 'period_month']);
            $table->index(['expense_id', 'period_month']);
        });

        Schema::create('partner_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('payout_method', 64)->nullable();
            $table->text('payout_details')->nullable(); // non-credential banking notes; not encrypted vault
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('distribution_runs', function (Blueprint $table) {
            $table->id();
            $table->date('period_month')->index();
            $table->string('status', 32)->default('draft')->index(); // draft|approved|voided
            $table->unsignedInteger('holdback_bps')->default(0); // reinvestment holdback
            $table->unsignedBigInteger('total_revenue_paisa')->default(0);
            $table->unsignedBigInteger('total_direct_expense_paisa')->default(0);
            $table->unsignedBigInteger('total_shared_expense_paisa')->default(0);
            $table->bigInteger('total_net_profit_paisa')->default(0);
            $table->unsignedBigInteger('total_holdback_paisa')->default(0);
            $table->unsignedBigInteger('total_credited_paisa')->default(0);
            $table->json('ownership_snapshot')->nullable(); // frozen on approve
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable()->index();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('voided_at')->nullable();
            $table->text('void_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['period_month', 'status']);
        });

        Schema::create('distribution_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('distribution_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('revenue_paisa')->default(0);
            $table->unsignedBigInteger('direct_expense_paisa')->default(0);
            $table->unsignedBigInteger('shared_expense_paisa')->default(0);
            $table->bigInteger('net_profit_paisa')->default(0);
            $table->unsignedInteger('share_bps')->default(0);
            $table->bigInteger('gross_share_paisa')->default(0);
            $table->unsignedBigInteger('holdback_paisa')->default(0);
            $table->bigInteger('credited_paisa')->default(0);
            $table->timestamps();

            $table->unique(['distribution_run_id', 'project_id', 'user_id'], 'dist_line_run_project_user_unique');
            $table->index(['user_id', 'distribution_run_id']);
            $table->index('project_id');
        });

        Schema::create('partner_ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 32)->index(); // capital_in|profit_credit|withdrawal|adjustment
            $table->bigInteger('amount_paisa'); // signed: + credit, − debit
            $table->bigInteger('balance_after_paisa');
            $table->date('entry_date')->index();
            $table->string('description');
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('distribution_run_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('distribution_line_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'entry_date']);
            $table->index(['user_id', 'id']);
            $table->index(['distribution_run_id', 'user_id']);
        });

        Schema::create('money_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 64)->index();
            $table->string('auditable_type')->nullable()->index();
            $table->unsignedBigInteger('auditable_id')->nullable()->index();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();

            $table->index(['auditable_type', 'auditable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('money_audit_logs');
        Schema::dropIfExists('partner_ledger_entries');
        Schema::dropIfExists('distribution_lines');
        Schema::dropIfExists('distribution_runs');
        Schema::dropIfExists('partner_profiles');
        Schema::dropIfExists('expense_allocations');

        Schema::table('expenses', function (Blueprint $table) {
            $table->dropForeign(['recurring_expense_id']);
            $table->dropForeign(['updated_by']);
            $table->dropColumn([
                'is_shared',
                'is_paid',
                'paid_at',
                'receipt_path',
                'receipt_original_name',
                'recurring_expense_id',
                'updated_by',
            ]);
        });

        Schema::dropIfExists('recurring_expenses');
        Schema::dropIfExists('revenues');
        Schema::dropIfExists('revenue_import_batches');
    }
};
