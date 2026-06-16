<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('legal_documents', function (Blueprint $table) {
            $table->boolean('ai_generated')->default(false)->after('portal_reviewed_at');
            $table->string('ai_review_status', 32)->nullable()->after('ai_generated');
            $table->foreignId('ai_governance_log_id')->nullable()->after('ai_review_status')
                ->constrained('ai_governance_logs')->nullOnDelete();
            $table->foreignId('ai_approved_by')->nullable()->after('ai_governance_log_id')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('ai_approved_at')->nullable()->after('ai_approved_by');
        });
    }

    public function down(): void
    {
        Schema::table('legal_documents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('ai_governance_log_id');
            $table->dropConstrainedForeignId('ai_approved_by');
            $table->dropColumn(['ai_generated', 'ai_review_status', 'ai_approved_at']);
        });
    }
};
