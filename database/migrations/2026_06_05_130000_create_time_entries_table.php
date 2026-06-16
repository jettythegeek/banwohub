<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('time_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('legal_matter_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('legal_task_id')->nullable()->constrained('legal_tasks')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('description')->nullable();
            $table->dateTime('started_at')->nullable();
            $table->dateTime('ended_at')->nullable();
            $table->unsignedInteger('duration_minutes')->default(0);
            $table->boolean('billable')->default(true);
            $table->decimal('rate', 12, 2)->nullable();
            $table->string('status')->default('draft');
            $table->boolean('is_running')->default(false);
            $table->dateTime('approved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'user_id']);
            $table->index(['organization_id', 'status']);
            $table->index(['legal_matter_id', 'billable']);
            $table->index(['user_id', 'is_running']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('time_entries');
    }
};
