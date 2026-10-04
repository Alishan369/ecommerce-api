<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

/** Branded, queued password-reset email (uses the storefront link from AppServiceProvider). */
class ResetPasswordLink extends ResetPassword implements ShouldQueue
{
    use Queueable;

    public function toMail($notifiable): MailMessage
    {
        $minutes = config('auth.passwords.'.config('auth.defaults.passwords').'.expire', 60);

        return (new MailMessage)
            ->subject('Reset your '.config('app.name').' password')
            ->greeting('Hello '.strtok((string) $notifiable->name, ' ').',')
            ->line('We received a request to reset the password for your account.')
            ->action('Choose a new password', $this->resetUrl($notifiable))
            ->line("This link expires in {$minutes} minutes and can only be used once.")
            ->line('If you didn\'t ask for this, you can ignore this email — your password won\'t change.')
            ->salutation('— The '.config('app.name').' team');
    }
}
