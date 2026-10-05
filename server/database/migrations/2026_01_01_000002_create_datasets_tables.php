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
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('department')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });

        Schema::create('dataset_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dataset_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version'); // 1, 2, 3...
            $table->string('file_path'); // storage path of the CSV data file
            $table->text('changelog')->nullable();
            $table->timestamps();

            $table->unique(['dataset_id', 'version']);
        });

        Schema::create('dataset_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dataset_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('label')->nullable();
            $table->text('description')->nullable();
            // restricted fields never appear in the public catalog preview
            // and require explicit approval before download
            $table->boolean('is_restricted')->default(false);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['dataset_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dataset_fields');
        Schema::dropIfExists('dataset_versions');
        Schema::dropIfExists('datasets');
    }
};
