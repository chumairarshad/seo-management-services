<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credentials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('type', 64)->index();
            $table->string('label');
            $table->text('username')->nullable();
            $table->text('secret')->nullable();
            $table->string('url')->nullable();
            $table->date('expires_on')->nullable()->index();
            $table->text('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['project_id', 'type']);
        });

        Schema::create('credential_access_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('credential_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('action', 32)->default('reveal')->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();

            $table->index(['credential_id', 'created_at']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credential_access_logs');
        Schema::dropIfExists('credentials');
    }
};
