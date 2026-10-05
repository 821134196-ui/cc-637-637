<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('access_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->foreignId('dataset_id')->constrained();
            // the exact version the researcher wants; pinned at apply time
            $table->foreignId('dataset_version_id')->constrained('dataset_versions');
            $table->text('purpose');
            // requested field names (JSON array)
            $table->json('fields');
            $table->date('access_start');
            $table->date('access_end');
            // pending -> approved | rejected. Rejected applications are immutable:
            // a changed purpose requires a brand new application.
            $table->string('status')->default('pending');
            $table->foreignId('decided_by')->nullable()->constrained('users');
            $table->timestamp('decided_at')->nullable();
            $table->text('decision_note')->nullable();
            $table->timestamps();
        });

        Schema::create('grants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained('access_applications');
            $table->foreignId('user_id')->constrained();
            $table->foreignId('dataset_id')->constrained();
            // snapshot of what was approved: version + fields + validity window.
            // Dataset updates never widen an existing grant.
            $table->foreignId('dataset_version_id')->constrained('dataset_versions');
            $table->json('fields');
            $table->timestamp('valid_from');
            $table->timestamp('valid_until');
            $table->string('status')->default('active'); // active | revoked
            $table->foreignId('revoked_by')->nullable()->constrained('users');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });

        Schema::create('download_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grant_id')->constrained()->cascadeOnDelete();
            $table->string('token', 64)->unique();
            $table->timestamps();
        });

        Schema::create('citations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            // the grant whose data was actually used -> pins dataset + version
            $table->foreignId('grant_id')->constrained();
            $table->foreignId('dataset_id')->constrained();
            $table->foreignId('dataset_version_id')->constrained('dataset_versions');
            $table->string('citation_number')->unique(); // e.g. DOI / report number
            $table->string('title');
            $table->string('venue')->nullable();
            $table->date('published_at')->nullable();
            // citations are a permanent registry: they survive grant
            // revocation and expiry.
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('citations');
        Schema::dropIfExists('download_tokens');
        Schema::dropIfExists('grants');
        Schema::dropIfExists('access_applications');
    }
};
