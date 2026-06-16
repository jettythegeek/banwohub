<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ediscovery_collections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('legal_matter_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('status')->default('open');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['organization_id', 'status'], 'ediscovery_coll_org_status_idx');
            $table->index(['legal_matter_id', 'status'], 'ediscovery_coll_matter_status_idx');
        });

        Schema::create('ediscovery_tags', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('legal_matter_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('color')->nullable();
            $table->string('category')->default('custom');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['organization_id', 'legal_matter_id', 'name'], 'ediscovery_tag_org_matter_name_uq');
            $table->index(['organization_id', 'category'], 'ediscovery_tag_org_category_idx');
        });

        Schema::create('ediscovery_documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('legal_matter_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ediscovery_collection_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('notes')->nullable();
            $table->date('document_date')->nullable();
            $table->string('sender')->nullable();
            $table->string('recipient')->nullable();
            $table->string('file_type')->default('other');
            $table->string('privilege')->default('none');
            $table->string('relevance')->default('needs_review');
            $table->json('custom_tags')->nullable();
            $table->string('review_status')->default('pending');
            $table->text('content_preview')->nullable();
            $table->string('original_filename')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->string('disk')->default('local');
            $table->string('path')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['organization_id', 'review_status'], 'ediscovery_doc_org_status_idx');
            $table->index(['legal_matter_id', 'privilege'], 'ediscovery_doc_matter_privilege_idx');
            $table->index(['legal_matter_id', 'relevance'], 'ediscovery_doc_matter_relevance_idx');
            $table->index(['legal_matter_id', 'review_status'], 'ediscovery_doc_matter_review_idx');
            $table->index(['ediscovery_collection_id', 'review_status'], 'ediscovery_doc_coll_review_idx');
            $table->index(['legal_matter_id', 'file_type'], 'ediscovery_doc_matter_filetype_idx');
            $table->index(['legal_matter_id', 'document_date'], 'ediscovery_doc_matter_date_idx');
        });

        Schema::create('ediscovery_review_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ediscovery_document_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reviewer_id')->constrained('users')->cascadeOnDelete();
            $table->string('review_status')->default('assigned');
            $table->text('notes')->nullable();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['ediscovery_document_id', 'reviewer_id'], 'ediscovery_review_doc_reviewer_uq');
            $table->index(['organization_id', 'reviewer_id', 'review_status'], 'ediscovery_review_org_reviewer_idx');
            $table->index(['ediscovery_document_id', 'review_status'], 'ediscovery_review_doc_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ediscovery_review_assignments');
        Schema::dropIfExists('ediscovery_documents');
        Schema::dropIfExists('ediscovery_tags');
        Schema::dropIfExists('ediscovery_collections');
    }
};
