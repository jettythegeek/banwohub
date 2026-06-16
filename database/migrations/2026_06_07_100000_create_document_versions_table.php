<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained('legal_documents')->cascadeOnDelete();
            $table->longText('content_html');
            $table->unsignedInteger('version_number');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('change_summary')->nullable();
            $table->timestamps();

            $table->unique(['document_id', 'version_number']);
            $table->index(['document_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_versions');
    }
};
