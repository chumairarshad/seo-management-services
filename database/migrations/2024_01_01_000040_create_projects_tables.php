<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('domain');
            $table->string('niche')->nullable();
            $table->string('cms')->nullable();
            $table->date('start_date')->nullable()->index();
            $table->unsignedBigInteger('acquisition_cost_paisa')->default(0);
            $table->string('status', 32)->default('setup')->index();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('domain');
        });

        Schema::create('project_owners', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('share_bps');
            $table->timestamps();

            $table->unique(['project_id', 'user_id']);
            $table->index('user_id');
        });

        Schema::create('project_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('assignment_note')->nullable();
            $table->timestamps();

            $table->unique(['project_id', 'user_id']);
            $table->index('user_id');
        });

        Schema::create('media', function (Blueprint $table) {
            $table->id();
            $table->morphs('mediable');
            $table->string('disk')->default('local');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('uploaded_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media');
        Schema::dropIfExists('project_user');
        Schema::dropIfExists('project_owners');
        Schema::dropIfExists('projects');
    }
};
