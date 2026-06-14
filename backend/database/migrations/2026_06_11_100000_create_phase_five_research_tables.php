<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legal_research_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->string('citation')->nullable();
            $table->text('summary')->nullable();
            $table->string('jurisdiction')->nullable();
            $table->string('document_type')->default('case');
            $table->json('tags')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['organization_id', 'document_type']);
            $table->index(['organization_id', 'jurisdiction']);
        });

        Schema::create('research_folders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('legal_matter_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('practice_area')->nullable();
            $table->string('legal_issue')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['organization_id', 'legal_matter_id']);
        });

        Schema::create('research_saved_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('research_folder_id')->constrained()->cascadeOnDelete();
            $table->foreignId('legal_research_entry_id')->constrained()->cascadeOnDelete();
            $table->foreignId('legal_matter_id')->nullable()->constrained()->cascadeOnDelete();
            $table->text('notes')->nullable();
            $table->foreignId('saved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['research_folder_id', 'legal_research_entry_id'], 'research_folder_entry_unique');
            $table->index(['organization_id', 'legal_matter_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('research_saved_items');
        Schema::dropIfExists('research_folders');
        Schema::dropIfExists('legal_research_entries');
    }
};
