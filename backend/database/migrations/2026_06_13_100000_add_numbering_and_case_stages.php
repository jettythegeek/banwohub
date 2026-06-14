<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->string('client_number', 32)->nullable()->after('organization_id');
            $table->unique(['organization_id', 'client_number']);
        });

        Schema::table('legal_matters', function (Blueprint $table) {
            $table->string('stage', 32)->default('lead')->after('status');
            $table->string('matter_stage', 64)->default('intake')->after('stage');
        });

        $this->backfillClientNumbers();
        $this->backfillCaseNumbers();
        $this->backfillStages();
    }

    public function down(): void
    {
        Schema::table('legal_matters', function (Blueprint $table) {
            $table->dropColumn(['stage', 'matter_stage']);
        });

        Schema::table('clients', function (Blueprint $table) {
            $table->dropUnique(['organization_id', 'client_number']);
            $table->dropColumn('client_number');
        });
    }

    protected function backfillClientNumbers(): void
    {
        $clients = DB::table('clients')
            ->whereNull('client_number')
            ->orderBy('organization_id')
            ->orderBy('id')
            ->get(['id', 'organization_id']);

        $counters = [];

        foreach ($clients as $client) {
            $orgId = (int) $client->organization_id;
            $counters[$orgId] = ($counters[$orgId] ?? 0) + 1;

            DB::table('clients')
                ->where('id', $client->id)
                ->update([
                    'client_number' => 'CL-'.str_pad((string) $counters[$orgId], 6, '0', STR_PAD_LEFT),
                ]);
        }
    }

    protected function backfillCaseNumbers(): void
    {
        $matters = DB::table('legal_matters')
            ->where(function ($q) {
                $q->whereNull('matter_number')
                    ->orWhere('matter_number', 'not like', 'CASE-%');
            })
            ->orderBy('organization_id')
            ->orderBy('id')
            ->get(['id', 'organization_id', 'matter_number']);

        $counters = [];

        foreach ($matters as $matter) {
            if (is_string($matter->matter_number) && str_starts_with($matter->matter_number, 'CASE-')) {
                continue;
            }

            $orgId = (int) $matter->organization_id;
            $counters[$orgId] = ($counters[$orgId] ?? 0) + 1;

            DB::table('legal_matters')
                ->where('id', $matter->id)
                ->update([
                    'matter_number' => 'CASE-'.str_pad((string) $counters[$orgId], 4, '0', STR_PAD_LEFT),
                ]);
        }
    }

    protected function backfillStages(): void
    {
        DB::table('legal_matters')->where('status', 'new')->update([
            'stage' => 'lead',
            'matter_stage' => 'intake',
        ]);

        DB::table('legal_matters')->whereIn('status', ['active', 'in_court', 'awaiting_client_response'])->update([
            'stage' => 'open',
        ]);

        DB::table('legal_matters')->whereIn('status', ['closed', 'archived'])->update([
            'stage' => 'closed',
            'matter_stage' => 'closed',
        ]);

        DB::table('legal_matters')
            ->where('stage', 'open')
            ->where('matter_stage', 'intake')
            ->update(['matter_stage' => 'active']);
    }
};
