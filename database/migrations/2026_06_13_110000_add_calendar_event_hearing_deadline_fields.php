<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('calendar_events', function (Blueprint $table) {
            $table->string('hearing_type')->nullable()->after('event_type');
            $table->string('hearing_status')->nullable()->after('hearing_type');
            $table->string('deadline_subtype')->nullable()->after('hearing_status');
            $table->string('court_name')->nullable()->after('location');
            $table->string('court_room')->nullable()->after('court_name');
            $table->string('judge_name')->nullable()->after('court_room');
            $table->unsignedSmallInteger('reminder_days_before')->nullable()->after('reminder_at');
        });
    }

    public function down(): void
    {
        Schema::table('calendar_events', function (Blueprint $table) {
            $table->dropColumn([
                'hearing_type',
                'hearing_status',
                'deadline_subtype',
                'court_name',
                'court_room',
                'judge_name',
                'reminder_days_before',
            ]);
        });
    }
};
