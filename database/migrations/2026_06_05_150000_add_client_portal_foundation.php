<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('client_id')
                ->nullable()
                ->after('organization_id')
                ->constrained('clients')
                ->nullOnDelete();
        });

        Schema::table('legal_documents', function (Blueprint $table) {
            $table->boolean('client_visible')->default(false)->after('version');
        });
    }

    public function down(): void
    {
        Schema::table('legal_documents', function (Blueprint $table) {
            $table->dropColumn('client_visible');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('client_id');
        });
    }
};
