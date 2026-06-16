<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legal_project_milestones', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('legal_matter_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('milestone_type')->default('custom');
            $table->string('status')->default('pending');
            $table->date('due_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['organization_id', 'legal_matter_id'], 'lpm_org_matter_idx');
            $table->index(['organization_id', 'status'], 'lpm_org_status_idx');
        });

        Schema::create('legal_project_budgets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('legal_matter_id')->constrained()->cascadeOnDelete();
            $table->string('category')->default('fees');
            $table->string('description');
            $table->decimal('budgeted_amount', 12, 2)->default(0);
            $table->decimal('actual_amount', 12, 2)->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['organization_id', 'legal_matter_id'], 'lpb_org_matter_idx');
        });

        Schema::create('training_courses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->longText('content')->nullable();
            $table->string('video_url')->nullable();
            $table->string('materials_url')->nullable();
            $table->decimal('cle_credits', 5, 2)->default(0);
            $table->boolean('is_required')->default(false);
            $table->boolean('is_published')->default(true);
            $table->json('quiz_questions')->nullable();
            $table->unsignedTinyInteger('passing_score')->default(70);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['organization_id', 'is_published'], 'tc_org_published_idx');
        });

        Schema::create('training_enrollments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('training_course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('assigned');
            $table->unsignedTinyInteger('quiz_score')->nullable();
            $table->decimal('cle_credits_earned', 5, 2)->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['organization_id', 'training_course_id', 'user_id'], 'training_enrollments_org_course_user_unique');
            $table->index(['organization_id', 'status'], 'te_org_status_idx');
        });

        Schema::create('training_certificates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('training_enrollment_id')->constrained()->cascadeOnDelete();
            $table->string('certificate_number')->unique();
            $table->timestamp('issued_at');
            $table->timestamps();

            $table->index(['organization_id', 'training_enrollment_id'], 'tcert_org_enroll_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_certificates');
        Schema::dropIfExists('training_enrollments');
        Schema::dropIfExists('training_courses');
        Schema::dropIfExists('legal_project_budgets');
        Schema::dropIfExists('legal_project_milestones');
    }
};
