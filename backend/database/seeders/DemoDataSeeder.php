<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\LegalMatter;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Demo client + case with canonical numbering (Wave 1 parity).
 * Run: php artisan db:seed --class=DemoDataSeeder
 */
class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $organization = Organization::query()->where('slug', 'banwolaw')->first();
        $admin = User::query()->where('email', 'admin@banwolaw.com')->first();

        if (! $organization || ! $admin) {
            $this->command?->warn('DemoDataSeeder skipped: run BanwolawSeeder first.');

            return;
        }

        $client = Client::query()->updateOrCreate(
            ['organization_id' => $organization->id, 'client_number' => 'CL-000001'],
            [
                'type' => 'individual',
                'name' => 'Demo Client',
                'email' => 'demo.client@banwolaw.test',
                'phone' => '+27 11 555 0100',
                'status' => 'active',
                'created_by' => $admin->id,
            ]
        );

        LegalMatter::query()->updateOrCreate(
            ['organization_id' => $organization->id, 'matter_number' => 'CASE-0001'],
            [
                'client_id' => $client->id,
                'title' => 'Demo Matter — Property Dispute',
                'stage' => 'open',
                'matter_stage' => 'active',
                'status' => 'active',
                'priority' => 'high',
                'practice_area' => 'Property',
                'case_type' => 'Civil',
                'opened_at' => now()->subDays(14)->toDateString(),
                'lead_lawyer_id' => $admin->id,
                'created_by' => $admin->id,
            ]
        );
    }
}
