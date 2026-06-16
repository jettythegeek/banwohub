<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('signature_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('document_id')->constrained('legal_documents')->cascadeOnDelete();
            $table->foreignId('legal_matter_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('status', 32)->default('pending');
            $table->json('fields');
            $table->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('signed_document_id')->nullable()->constrained('legal_documents')->nullOnDelete();
            $table->timestamp('signed_at')->nullable();
            $table->string('signer_ip', 45)->nullable();
            $table->json('audit')->nullable();
            $table->text('message')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'status']);
            $table->index(['client_id', 'status']);
            $table->index(['legal_matter_id', 'status']);
            $table->index('document_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('signature_requests');
    }
};
