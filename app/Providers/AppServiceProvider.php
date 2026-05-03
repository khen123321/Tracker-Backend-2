<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Auth\Notifications\VerifyEmail; // ✨ Add this import
use Illuminate\Notifications\Messages\MailMessage; // ✨ Add this import
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\URL;

class AppServiceProvider extends ServiceProvider
{
    public function register()
    {
        //
    }

    public function boot()
    {
        // ✨ 1. Hijack the verification email link and point it to React!
        VerifyEmail::createUrlUsing(function ($notifiable) {
            
            // Generate the secure, signed API link that Laravel needs
            $apiVerifyUrl = URL::temporarySignedRoute(
                'verification.verify', 
                Carbon::now()->addMinutes(Config::get('auth.verification.expire', 60)),
                [
                    'id' => $notifiable->getKey(),
                    'hash' => sha1($notifiable->getEmailForVerification()),
                ]
            );

            // Pull the exact React URL from your .env file
            $reactAppUrl = env('FRONTEND_URL', 'http://localhost:5173'); 

            // We pass the full Laravel API url to React as a query parameter
            return $reactAppUrl . '/verify-email?api_url=' . urlencode($apiVerifyUrl);
        });

        // ✨ 2. Point to your new custom Blade template with the footer!
        VerifyEmail::toMailUsing(function ($notifiable, $url) {
            
            // Determine the subject based on the role
            $subject = in_array($notifiable->role, ['hr', 'hr_intern', 'superadmin']) 
                ? 'Verify Your CLIMBS Admin Account' 
                : 'Verify Your CLIMBS Internship Account';

            // Instead of chaining ->line(), we pass all the data directly to our new Blade view!
            return (new MailMessage)
                ->subject($subject)
                ->markdown('emails.verify', [
                    'url' => $url,
                    'user' => $notifiable // We pass the whole user object to the view
                ]);
        });
    }
}