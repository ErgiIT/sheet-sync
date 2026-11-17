<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        Schema::create('spreadsheets', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('google_spreadsheet_id')->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
        });

        Schema::create('spreadsheet_tabs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('spreadsheet_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedSmallInteger('header_row')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['spreadsheet_id', 'name']);
        });

        Schema::create('tab_mappings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('spreadsheet_tab_id')->constrained()->cascadeOnDelete();
            $table->string('target_table', 64);
            $table->string('sync_mode', 16)->default('replace');
            $table->json('key_columns')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['spreadsheet_tab_id', 'target_table']);
        });

        Schema::create('column_mappings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tab_mapping_id')->constrained()->cascadeOnDelete();
            $table->string('source_column');
            $table->string('target_column', 64);
            $table->string('type', 16)->default('string');
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['tab_mapping_id', 'target_column']);
        });

        Schema::create('sync_runs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('spreadsheet_id')->constrained()->cascadeOnDelete();
            $table->string('trigger', 16);
            $table->string('status', 16)->index();
            $table->unsignedInteger('rows_read')->default(0);
            $table->unsignedInteger('rows_written')->default(0);
            $table->json('messages')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_runs');
        Schema::dropIfExists('column_mappings');
        Schema::dropIfExists('tab_mappings');
        Schema::dropIfExists('spreadsheet_tabs');
        Schema::dropIfExists('spreadsheets');
    }
};
