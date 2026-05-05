<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Auth\Notifications\ResetPassword; // ✨ NEW: Required for Forgot Password
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // ✨ 1. Intercept Email Verification 
        VerifyEmail::createUrlUsing(function ($notifiable) {
            
            // Generate the secure, signed link Laravel expects
            $apiVerifyUrl = URL::temporarySignedRoute(
                'verification.verify',
                Carbon::now()->addMinutes(Config::get('auth.verification.expire', 60)),
                [
                    'id' => $notifiable->getKey(),
                    'hash' => sha1($notifiable->getEmailForVerification()),
                ]
            );

            // Send it to React with the API URL attached as a safe parameter
            $frontendUrl = env('FRONTEND_URL', 'https://localhost:5173');
            return $frontendUrl . '/verify-email?api_url=' . urlencode($apiVerifyUrl);
        });

        // ✨ 2. Intercept Reset Password
        ResetPassword::createUrlUsing(function ($notifiable, $token) {
            
            $frontendUrl = env('FRONTEND_URL', 'https://localhost:5173');
            
            // Attach the secure token and the user's email directly to the React URL
            return $frontendUrl . '/reset-password?token=' . $token . '&email=' . urlencode($notifiable->getEmailForPasswordReset());
        });
    }
}