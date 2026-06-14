<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('legal_documents', function (Blueprint $table) {
            $table->boolean('uploaded_by_client')->default(false)->after('client_visible');
            $table->timestamp('portal_reviewed_at')->nullable()->after('uploaded_by_client');
        });
    }

    public function down(): void
    {
        Schema::table('legal_documents', function (Blueprint $table) {
            $table->dropColumn(['uploaded_by_client', 'portal_reviewed_at']);
        });
    }
};
