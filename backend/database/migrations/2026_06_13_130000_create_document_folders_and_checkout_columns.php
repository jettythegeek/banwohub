<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_folders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('legal_matter_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('document_folders')->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();

            $table->index(['legal_matter_id', 'parent_id']);
            $table->index(['organization_id', 'legal_matter_id']);
        });

        Schema::table('legal_documents', function (Blueprint $table) {
            $table->foreignId('document_folder_id')->nullable()->after('legal_matter_id')->constrained('document_folders')->nullOnDelete();
            $table->foreignId('checked_out_by')->nullable()->after('uploaded_by')->constrained('users')->nullOnDelete();
            $table->timestamp('checked_out_at')->nullable()->after('checked_out_by');
        });
    }

    public function down(): void
    {
        Schema::table('legal_documents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('document_folder_id');
            $table->dropConstrainedForeignId('checked_out_by');
            $table->dropColumn('checked_out_at');
        });

        Schema::dropIfExists('document_folders');
    }
};
