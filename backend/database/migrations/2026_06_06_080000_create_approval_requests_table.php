<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approval_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('subject_type', 64);
            $table->unsignedBigInteger('subject_id');
            $table->string('status', 32)->default('draft');
            $table->boolean('requires_approval')->default(true);
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('comments')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['subject_type', 'subject_id']);
            $table->index(['organization_id', 'status']);
        });

        Schema::table('legal_documents', function (Blueprint $table) {
            $table->boolean('requires_approval')->default(false)->after('ai_approved_at');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->boolean('requires_approval')->default(false)->after('paid_at');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('requires_approval');
        });

        Schema::table('legal_documents', function (Blueprint $table) {
            $table->dropColumn('requires_approval');
        });

        Schema::dropIfExists('approval_requests');
    }
};
