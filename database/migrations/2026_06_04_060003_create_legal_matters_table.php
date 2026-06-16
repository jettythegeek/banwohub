<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legal_matters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('matter_number')->nullable();
            $table->string('practice_area')->nullable();
            $table->string('case_type')->nullable();
            $table->string('court_jurisdiction')->nullable();
            $table->string('status')->default('new');
            $table->string('priority')->default('normal');
            $table->date('opened_at')->nullable();
            $table->date('expected_close_at')->nullable();
            $table->text('description')->nullable();
            $table->json('tags')->nullable();
            $table->foreignId('lead_lawyer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'matter_number']);
            $table->index(['organization_id', 'status']);
        });

        Schema::create('legal_matter_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('legal_matter_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role')->default('support');
            $table->timestamps();

            $table->unique(['legal_matter_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legal_matter_user');
        Schema::dropIfExists('legal_matters');
    }
};
