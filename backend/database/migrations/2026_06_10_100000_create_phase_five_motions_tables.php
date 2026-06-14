<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('motion_templates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->string('slug');
            $table->string('name');
            $table->text('description')->nullable();
            $table->longText('structure_html')->nullable();
            $table->json('required_sections')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['organization_id', 'slug']);
            $table->index(['organization_id', 'is_active']);
        });

        Schema::create('legal_motions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('legal_matter_id')->constrained()->cascadeOnDelete();
            $table->foreignId('motion_template_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->string('motion_type')->nullable();
            $table->longText('content_html')->nullable();
            $table->string('status')->default('draft');
            $table->foreignId('court_filing_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('last_ai_governance_log_id')->nullable()->constrained('ai_governance_logs')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['organization_id', 'status']);
            $table->index(['legal_matter_id', 'status']);
        });

        Schema::table('court_filings', function (Blueprint $table): void {
            $table->foreignId('legal_motion_id')->nullable()->after('court_form_instance_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('court_filings', function (Blueprint $table): void {
            $table->dropForeign(['legal_motion_id']);
            $table->dropColumn('legal_motion_id');
        });

        Schema::dropIfExists('legal_motions');
        Schema::dropIfExists('motion_templates');
    }
};
