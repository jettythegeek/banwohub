<?php

/**
 * Dev-only: exercises forgot + reset API. Changes the user's password to the second CLI arg
 * (default NewTestPass123!). Re-seed to restore demo login: php artisan migrate:fresh --seed
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$email = $argv[1] ?? 'admin@banwolaw.com';
$newPassword = $argv[2] ?? 'NewTestPass123!';

$forgot = $app->handle(
    Illuminate\Http\Request::create(
        '/api/v1/auth/forgot-password',
        'POST',
        [],
        [],
        [],
        ['HTTP_ACCEPT' => 'application/json', 'CONTENT_TYPE' => 'application/json'],
        json_encode(['email' => $email], JSON_THROW_ON_ERROR),
    ),
);

if ($forgot->getStatusCode() !== 200) {
    fwrite(STDERR, "forgot failed: {$forgot->getStatusCode()} {$forgot->getContent()}\n");
    exit(1);
}

$body = json_decode($forgot->getContent(), true);
if (! is_array($body) || empty($body['reset_link'])) {
    fwrite(STDERR, "forgot ok but no reset_link (set APP_DEBUG=true): {$forgot->getContent()}\n");
    exit(1);
}

parse_str((string) parse_url($body['reset_link'], PHP_URL_QUERY), $query);
$token = $query['token'] ?? null;
$resetEmail = isset($query['email']) ? urldecode((string) $query['email']) : $email;

if (! $token) {
    fwrite(STDERR, "could not parse token from reset_link\n");
    exit(1);
}

$reset = $app->handle(
    Illuminate\Http\Request::create(
        '/api/v1/auth/reset-password',
        'POST',
        [],
        [],
        [],
        ['HTTP_ACCEPT' => 'application/json', 'CONTENT_TYPE' => 'application/json'],
        json_encode([
            'token' => $token,
            'email' => $resetEmail,
            'password' => $newPassword,
            'password_confirmation' => $newPassword,
        ], JSON_THROW_ON_ERROR),
    ),
);

if ($reset->getStatusCode() !== 200) {
    fwrite(STDERR, "reset failed: {$reset->getStatusCode()} {$reset->getContent()}\n");
    exit(1);
}

$user = App\Models\User::query()->where('email', $email)->first();
$ok = $user && Illuminate\Support\Facades\Hash::check($newPassword, $user->password);

echo $ok ? "forgot_reset_ok\n" : "forgot_reset_fail\n";
exit($ok ? 0 : 1);
