<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\LegalMatter;
use App\Models\LegalTask;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Minimal fixtures for Playwright smoke tests (Slice 13).
 * Run after DatabaseSeeder: php artisan db:seed --class=E2eSmokeSeeder
 */
class E2eSmokeSeeder extends Seeder
{
    public const CLIENT_EMAIL = 'e2e-smoke-client@banwolaw.test';

    public const MATTER_NUMBER = 'E2E-SMOKE-001';

    public const MATTER_TITLE = 'E2E Smoke Case';

    public const TASK_TITLE = 'E2E Smoke Task';

    public function run(): void
    {
        $organization = Organization::query()->where('slug', 'banwolaw')->first();
        $admin = User::query()->where('email', 'admin@banwolaw.com')->first();

        if (! $organization || ! $admin) {
            $this->command?->warn('E2eSmokeSeeder skipped: run BanwolawSeeder first.');

            return;
        }

        $client = Client::query()->updateOrCreate(
            ['organization_id' => $organization->id, 'email' => self::CLIENT_EMAIL],
            [
                'type' => 'individual',
                'name' => 'E2E Smoke Client',
                'status' => 'active',
                'created_by' => $admin->id,
            ]
        );

        $matter = LegalMatter::query()->updateOrCreate(
            ['organization_id' => $organization->id, 'matter_number' => self::MATTER_NUMBER],
            [
                'client_id' => $client->id,
                'title' => self::MATTER_TITLE,
                'status' => 'active',
                'priority' => 'normal',
                'created_by' => $admin->id,
            ]
        );

        LegalTask::query()->updateOrCreate(
            [
                'organization_id' => $organization->id,
                'legal_matter_id' => $matter->id,
                'title' => self::TASK_TITLE,
            ],
            [
                'assignee_id' => $admin->id,
                'created_by' => $admin->id,
                'status' => 'not_started',
                'priority' => 'normal',
            ]
        );
    }
}
