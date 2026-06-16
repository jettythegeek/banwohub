<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('legal_matters', function (Blueprint $table) {
            $table->decimal('trust_balance', 12, 2)->nullable()->after('retainer_minimum_amount');
        });

        Schema::create('trust_ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('legal_matter_id')->constrained()->cascadeOnDelete();
            $table->string('entry_type', 32);
            $table->decimal('amount', 12, 2);
            $table->string('description')->nullable();
            $table->timestamp('occurred_at')->nullable();
            $table->timestamps();

            $table->index(['legal_matter_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trust_ledger_entries');

        Schema::table('legal_matters', function (Blueprint $table) {
            $table->dropColumn('trust_balance');
        });
    }
};
