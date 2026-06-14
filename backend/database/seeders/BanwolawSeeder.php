<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Seeder;
class BanwolawSeeder extends Seeder
{
    public function run(): void
    {
        $organization = Organization::query()->updateOrCreate(
            ['slug' => 'banwolaw'],
            [
                'name' => 'Banwolaw',
                'legal_name' => 'Banwolaw Legal Practice',
                'email' => 'info@banwolaw.com',
                'phone' => null,
                'address' => null,
                'practice_areas' => ['Litigation', 'Corporate', 'Property', 'Family'],
                'case_types' => ['Civil', 'Criminal', 'Commercial', 'Probate'],
                'jurisdictions' => ['High Court', 'Magistrate Court'],
                'settings' => [
                    'timezone' => 'Africa/Johannesburg',
                    'currency' => 'USD',
                ],
            ]
        );

        $adminPassword = env('SEED_ADMIN_PASSWORD', 'ChangeMe123!');
        $admin = User::query()->updateOrCreate(
            ['email' => 'admin@banwolaw.com'],
            [
                'organization_id' => $organization->id,
                'name' => 'Banwolaw Admin',
                'job_title' => 'Managing Partner',
                'is_active' => true,
            ]
        );
        $admin->forceFill(['password' => $adminPassword])->save();

        if (! $admin->hasRole('Firm Admin')) {
            $admin->assignRole('Firm Admin');
        }

        $sysadminPassword = env('SEED_SYSADMIN_PASSWORD', 'ChangeMe123!');
        $systemAdmin = User::query()->updateOrCreate(
            ['email' => 'sysadmin@banwolaw.com'],
            [
                'organization_id' => $organization->id,
                'name' => 'System Administrator',
                'job_title' => 'IT Admin',
                'is_active' => true,
            ]
        );
        $systemAdmin->forceFill(['password' => $sysadminPassword])->save();

        if (! $systemAdmin->hasRole('System Admin')) {
            $systemAdmin->assignRole('System Admin');
        }

        $consultantPassword = env('SEED_CONSULTANT_PASSWORD', 'ChangeMe123!');
        $consultant = User::query()->updateOrCreate(
            ['email' => 'consultant@banwolaw.com'],
            [
                'organization_id' => $organization->id,
                'name' => 'External Consultant',
                'job_title' => 'Consultant',
                'is_active' => true,
                'password' => $consultantPassword,
            ]
        );
        $consultant->forceFill(['password' => $consultantPassword])->save();

        if (! $consultant->hasRole('Consultant')) {
            $consultant->assignRole('Consultant');
        }
    }
}
