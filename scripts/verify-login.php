<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$email = $argv[1] ?? 'admin@banwolaw.com';
$password = $argv[2] ?? getenv('SEED_ADMIN_PASSWORD') ?: 'ChangeMe123!';

$user = App\Models\User::query()->where('email', $email)->first();
if (! $user) {
    fwrite(STDERR, "User not found: {$email}\n");
    exit(1);
}

$ok = Illuminate\Support\Facades\Hash::check($password, $user->password);
echo $ok ? "password_ok\n" : "password_fail\n";
exit($ok ? 0 : 1);
