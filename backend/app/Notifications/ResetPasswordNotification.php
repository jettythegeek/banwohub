<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Log;

class ResetPasswordNotification extends ResetPassword
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [];
    }

    protected function resetUrl($notifiable): string
    {
        $base = rtrim(config('app.password_reset_frontend_url'), '/');
        $email = urlencode($notifiable->getEmailForPasswordReset());
        $url = "{$base}?token={$this->token}&email={$email}";

        Log::info('Password reset link generated', [
            'email' => $notifiable->getEmailForPasswordReset(),
            'url' => $url,
        ]);

        return $url;
    }
}
