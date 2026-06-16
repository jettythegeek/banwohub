<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('communication_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('legal_matter_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('message_thread_id')->nullable()->constrained()->nullOnDelete();
            $table->string('channel');
            $table->string('subject')->nullable();
            $table->text('body')->nullable();
            $table->foreignId('logged_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('occurred_at');
            $table->text('client_feedback')->nullable();
            $table->unsignedTinyInteger('satisfaction_score')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'client_id']);
            $table->index(['client_id', 'occurred_at']);
            $table->index(['organization_id', 'channel']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('communication_logs');
    }
};
