<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evidence_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('legal_matter_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('evidence_type');
            $table->string('source')->nullable();
            $table->date('date_obtained')->nullable();
            $table->string('relevance')->nullable();
            $table->string('exhibit_number')->nullable();
            $table->json('tags')->nullable();
            $table->string('status')->default('uploaded');
            $table->string('original_filename')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->string('disk')->default('local');
            $table->string('path')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['organization_id', 'status'], 'evidence_org_status_idx');
            $table->index(['legal_matter_id', 'status'], 'evidence_matter_status_idx');
            $table->index(['legal_matter_id', 'exhibit_number'], 'evidence_matter_exhibit_idx');
        });

        Schema::create('evidence_custody_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('evidence_item_id')->constrained()->cascadeOnDelete();
            $table->string('action');
            $table->text('notes')->nullable();
            $table->string('location')->nullable();
            $table->foreignId('from_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('to_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('logged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('logged_at');
            $table->timestamps();

            $table->index(['evidence_item_id', 'logged_at'], 'evidence_custody_item_logged_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evidence_custody_logs');
        Schema::dropIfExists('evidence_items');
    }
};
