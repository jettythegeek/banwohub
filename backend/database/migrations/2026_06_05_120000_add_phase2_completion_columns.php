<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('legal_documents', function (Blueprint $table) {
            $table->longText('content_html')->nullable()->after('description');
            $table->foreignId('parent_template_id')->nullable()->after('content_html')
                ->constrained('legal_documents')->nullOnDelete();
        });

        Schema::table('calendar_events', function (Blueprint $table) {
            $table->timestamp('reminder_sent_at')->nullable()->after('reminder_at');
        });

        Schema::table('legal_tasks', function (Blueprint $table) {
            $table->dropForeign(['legal_matter_id']);
        });
        DB::statement('ALTER TABLE legal_tasks MODIFY legal_matter_id BIGINT UNSIGNED NULL');
        Schema::table('legal_tasks', function (Blueprint $table) {
            $table->foreign('legal_matter_id')->references('id')->on('legal_matters')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('legal_tasks', function (Blueprint $table) {
            $table->dropForeign(['legal_matter_id']);
        });
        DB::statement('ALTER TABLE legal_tasks MODIFY legal_matter_id BIGINT UNSIGNED NOT NULL');
        Schema::table('legal_tasks', function (Blueprint $table) {
            $table->foreign('legal_matter_id')->references('id')->on('legal_matters')->cascadeOnDelete();
        });

        Schema::table('calendar_events', function (Blueprint $table) {
            $table->dropColumn('reminder_sent_at');
        });

        Schema::table('legal_documents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parent_template_id');
            $table->dropColumn('content_html');
        });
    }
};
