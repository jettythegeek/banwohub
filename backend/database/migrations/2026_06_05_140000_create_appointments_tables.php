<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('calendar_events', function (Blueprint $table) {
            $table->dropForeign(['legal_matter_id']);
        });
        DB::statement('ALTER TABLE calendar_events MODIFY legal_matter_id BIGINT UNSIGNED NULL');
        Schema::table('calendar_events', function (Blueprint $table) {
            $table->foreign('legal_matter_id')->references('id')->on('legal_matters')->cascadeOnDelete();
        });

        Schema::create('lawyer_availability_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('day_of_week');
            $table->time('start_time');
            $table->time('end_time');
            $table->unsignedSmallInteger('slot_duration_minutes')->default(30);
            $table->json('consultation_types')->nullable();
            $table->decimal('consultation_fee', 12, 2)->nullable();
            $table->string('location')->nullable();
            $table->boolean('online_meeting')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['organization_id', 'user_id', 'day_of_week'], 'lawyer_avail_org_user_dow_idx');
        });

        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('calendar_event_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('legal_matter_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('booked_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('consultation_type');
            $table->string('status')->default('pending');
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('location')->nullable();
            $table->boolean('online_meeting')->default(false);
            $table->decimal('fee', 12, 2)->nullable();
            $table->string('payment_status')->default('none');
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'starts_at']);
            $table->index(['user_id', 'starts_at']);
            $table->index(['client_id', 'starts_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointments');
        Schema::dropIfExists('lawyer_availability_slots');

        Schema::table('calendar_events', function (Blueprint $table) {
            $table->dropForeign(['legal_matter_id']);
        });
        DB::statement('ALTER TABLE calendar_events MODIFY legal_matter_id BIGINT UNSIGNED NOT NULL');
        Schema::table('calendar_events', function (Blueprint $table) {
            $table->foreign('legal_matter_id')->references('id')->on('legal_matters')->cascadeOnDelete();
        });
    }
};
