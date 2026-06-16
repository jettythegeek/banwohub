<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('legal_matter_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('party_type');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['legal_matter_id', 'party_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parties');
    }
};
