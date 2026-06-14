<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_governance_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action_type', 50);
            $table->string('bot_context', 30)->nullable();
            $table->foreignId('legal_matter_id')->nullable()->constrained('legal_matters')->nullOnDelete();
            $table->foreignId('legal_document_id')->nullable()->constrained('legal_documents')->nullOnDelete();
            $table->string('output_id', 64)->nullable();
            $table->string('model', 100)->nullable();
            $table->string('status', 20)->default('success');
            $table->text('output_preview')->nullable();
            $table->json('prompt_context')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'created_at']);
            $table->index(['user_id', 'action_type']);
            $table->index('output_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_governance_logs');
    }
};
