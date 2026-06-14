<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('legal_briefs', function (Blueprint $table): void {
            $table->string('brief_type')->default('memorandum_of_law')->after('title');
            $table->string('jurisdiction')->nullable()->after('brief_type');
            $table->string('court_type')->default('federal')->after('jurisdiction');
            $table->string('cause_of_action')->nullable()->after('court_type');
            $table->text('case_facts')->nullable()->after('cause_of_action');
            $table->text('statutes')->nullable()->after('case_facts');
            $table->text('desired_outcome')->nullable()->after('statutes');
            $table->string('citation_style')->default('bluebook')->after('desired_outcome');

            $table->index(['organization_id', 'brief_type']);
            $table->index(['organization_id', 'court_type']);
        });

        Schema::create('research_projects', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('legal_matter_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->text('case_theory')->nullable();
            $table->string('jurisdiction')->nullable();
            $table->string('practice_area')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['organization_id', 'legal_matter_id']);
        });

        Schema::create('research_chat_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('research_project_id')->constrained()->cascadeOnDelete();
            $table->string('role');
            $table->longText('content');
            $table->foreignId('ai_governance_log_id')->nullable()->constrained('ai_governance_logs')->nullOnDelete();
            $table->timestamps();

            $table->index(['research_project_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('research_chat_messages');
        Schema::dropIfExists('research_projects');

        Schema::table('legal_briefs', function (Blueprint $table): void {
            $table->dropIndex(['organization_id', 'brief_type']);
            $table->dropIndex(['organization_id', 'court_type']);
            $table->dropColumn([
                'brief_type',
                'jurisdiction',
                'court_type',
                'cause_of_action',
                'case_facts',
                'statutes',
                'desired_outcome',
                'citation_style',
            ]);
        });
    }
};
