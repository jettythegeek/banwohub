<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_provider_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 30);
            $table->text('api_key')->nullable();
            $table->boolean('is_enabled')->default(false);
            $table->string('model', 100)->nullable();
            $table->json('settings')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'provider']);
            $table->index(['organization_id', 'is_enabled']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_provider_configs');
    }
};
