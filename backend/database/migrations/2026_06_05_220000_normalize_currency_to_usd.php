<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('invoices')
            ->where(function ($query): void {
                $query->whereNull('currency')
                    ->orWhere('currency', '!=', 'USD');
            })
            ->update(['currency' => 'USD']);

        foreach (DB::table('organizations')->select('id', 'settings')->get() as $organization) {
            $settings = json_decode($organization->settings ?? '{}', true);
            if (! is_array($settings)) {
                $settings = [];
            }

            if (($settings['currency'] ?? null) === 'USD') {
                continue;
            }

            $settings['currency'] = 'USD';

            DB::table('organizations')
                ->where('id', $organization->id)
                ->update(['settings' => json_encode($settings)]);
        }
    }

    public function down(): void
    {
        // Currency normalization is intentionally not reversed (amounts unchanged).
    }
};
