<?php

declare(strict_types=1);

/**
 * Reset admin@banwolaw.com password without wiping the database.
 * Usage: php scripts/reset-admin-password.php [password]
 */

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$email = 'admin@banwolaw.com';
$password = $argv[1] ?? getenv('SEED_ADMIN_PASSWORD') ?: 'ChangeMe123!';

$user = App\Models\User::query()->where('email', $email)->first();
if (! $user) {
    fwrite(STDERR, "User not found: {$email}. Run: php artisan db:seed --class=BanwolawSeeder\n");
    exit(1);
}

$user->forceFill(['password' => $password, 'is_active' => true])->save();

$ok = Illuminate\Support\Facades\Hash::check($password, $user->fresh()->password);
echo $ok ? "password_reset_ok for {$email}\n" : "password_reset_fail\n";
exit($ok ? 0 : 1);
