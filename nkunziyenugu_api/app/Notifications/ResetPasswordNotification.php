<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

class ResetPasswordNotification extends ResetPassword
{
    use Queueable;

    public function toMail($notifiable)
    {
        $url = config('app.frontend_url')
            . '/ResetPassword'
            . '?token=' . $this->token
            . '&email=' . urlencode($notifiable->email);

        return (new MailMessage)
            ->subject('Reset Your Password')
            ->line('You requested a password reset.')
            ->action('Reset Password', $url)
            ->line('If you did not request this, ignore this email.');
    }
}
