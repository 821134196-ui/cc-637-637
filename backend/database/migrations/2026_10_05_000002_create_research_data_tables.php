<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('datasets', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('dataset_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dataset_id')->constrained()->cascadeOnDelete();
            $table->string('version');
            $table->string('file_path');
            $table->text('changelog')->nullable();
            $table->timestamps();

            $table->unique(['dataset_id', 'version']);
        });

        Schema::create('dataset_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dataset_version_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('label')->nullable();
            $table->string('data_type')->default('string');
            $table->boolean('is_restricted')->default(false);
            $table->string('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['dataset_version_id', 'name']);
        });

        Schema::create('access_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('dataset_version_id')->constrained()->restrictOnDelete();
            $table->text('purpose');
            $table->json('requested_fields');
            $table->date('period_start');
            $table->date('period_end');
            $table->string('status')->default('pending'); // pending | approved | rejected
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->text('decision_reason')->nullable();
            $table->timestamps();
        });

        Schema::create('access_grants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('access_request_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('dataset_version_id')->constrained()->restrictOnDelete();
            $table->json('granted_fields'); // snapshot at approval time
            $table->date('period_start');
            $table->date('period_end');
            $table->timestamp('revoked_at')->nullable();
            $table->text('revoke_reason')->nullable();
            $table->timestamps();
        });

        Schema::create('download_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('access_grant_id')->constrained()->cascadeOnDelete();
            $table->string('token_hash', 64)->unique();
            $table->string('label')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        Schema::create('citations', function (Blueprint $table) {
            $table->id();
            $table->string('citation_number')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('access_grant_id')->constrained()->restrictOnDelete();
            $table->foreignId('dataset_version_id')->constrained()->restrictOnDelete();
            $table->string('title');
            $table->string('venue')->nullable();
            $table->string('url')->nullable();
            $table->date('published_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('citations');
        Schema::dropIfExists('download_tokens');
        Schema::dropIfExists('access_grants');
        Schema::dropIfExists('access_requests');
        Schema::dropIfExists('dataset_fields');
        Schema::dropIfExists('dataset_versions');
        Schema::dropIfExists('datasets');
    }
};
