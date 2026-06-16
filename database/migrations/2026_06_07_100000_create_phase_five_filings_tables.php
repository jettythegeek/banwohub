<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('court_form_templates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('jurisdiction');
            $table->string('court')->nullable();
            $table->string('case_type')->nullable();
            $table->string('filing_type')->nullable();
            $table->json('fields');
            $table->json('guidance')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['organization_id', 'jurisdiction', 'is_active'], 'court_form_tpl_org_jurisdiction_active_idx');
        });

        Schema::create('court_filings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('legal_matter_id')->constrained()->cascadeOnDelete();
            $table->foreignId('court_form_instance_id')->nullable();
            $table->string('title');
            $table->string('court');
            $table->date('filing_date')->nullable();
            $table->foreignId('filed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('filing_method')->default('manual');
            $table->string('court_reference_number')->nullable();
            $table->json('document_ids')->nullable();
            $table->string('status')->default('draft');
            $table->text('court_response')->nullable();
            $table->text('notes')->nullable();
            $table->date('correction_deadline')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['organization_id', 'status']);
            $table->index(['legal_matter_id', 'status']);
        });

        Schema::create('court_form_instances', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('legal_matter_id')->constrained()->cascadeOnDelete();
            $table->foreignId('court_form_template_id')->constrained()->cascadeOnDelete();
            $table->foreignId('court_filing_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->json('field_values')->nullable();
            $table->string('status')->default('draft');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['organization_id', 'legal_matter_id']);
        });

        Schema::table('court_filings', function (Blueprint $table): void {
            $table->foreign('court_form_instance_id')
                ->references('id')
                ->on('court_form_instances')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('court_filings', function (Blueprint $table): void {
            $table->dropForeign(['court_form_instance_id']);
        });

        Schema::dropIfExists('court_form_instances');
        Schema::dropIfExists('court_filings');
        Schema::dropIfExists('court_form_templates');
    }
};
