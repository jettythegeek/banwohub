<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legal_briefs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('legal_matter_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->longText('content_html')->nullable();
            $table->string('status')->default('draft');
            $table->foreignId('last_ai_governance_log_id')->nullable()->constrained('ai_governance_logs')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['organization_id', 'status']);
            $table->index(['legal_matter_id', 'status']);
        });

        Schema::create('brief_citations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('legal_brief_id')->constrained()->cascadeOnDelete();
            $table->string('authority');
            $table->text('citation_text');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->text('source_note')->nullable();
            $table->timestamps();

            $table->index(['legal_brief_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('brief_citations');
        Schema::dropIfExists('legal_briefs');
    }
};
