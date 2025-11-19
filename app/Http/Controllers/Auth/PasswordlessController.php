<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Carbon\Carbon;

class PasswordlessController extends Controller
{
    /**
     * Show the passwordless login form
     */
    public function showLoginForm()
    {
        return view('auth.passwordless.login');
    }

    /**
     * Send magic link to user's email
     */
    public function sendMagicLink(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:restaurant_users,email',
        ], [
            'email.required' => 'Email address is required.',
            'email.email' => 'Please enter a valid email address.',
            'email.exists' => 'We cannot find a user with that email address.',
        ]);

        $email = $request->email;
        $key = 'magic-link:' . $email;

        // Rate limiting: max 3 attempts per 5 minutes
        if (RateLimiter::tooManyAttempts($key, 3)) {
            $seconds = RateLimiter::availableIn($key);
            return redirect()->back()
                ->with('error', "Too many requests. Please try again in {$seconds} seconds.");
        }

        // Generate magic link token
        $token = Str::random(64);
        $expiresAt = Carbon::now()->addMinutes(15); // 15 minutes expiry

        // Store token in cache
        cache()->put("magic-link:{$token}", [
            'email' => $email,
            'expires_at' => $expiresAt,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ], $expiresAt);

        // Generate magic link URL
        $magicLink = route('passwordless.verify', ['token' => $token]);

        // Send email
        try {
            Mail::send('emails.magic-link', [
                'magicLink' => $magicLink,
                'expiresAt' => $expiresAt,
                'ip' => $request->ip(),
            ], function ($message) use ($email) {
                $message->to($email)
                        ->subject('Your Magic Login Link - ' . config('app.name'));
            });

            // Increment rate limiter
            RateLimiter::hit($key, 300); // 5 minutes

            Log::info('Magic link sent', [
                'email' => $email,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'expires_at' => $expiresAt
            ]);

            return redirect()->back()
                ->with('success', 'A magic login link has been sent to your email address. Please check your inbox and click the link to sign in.');

        } catch (\Exception $e) {
            Log::error('Failed to send magic link', [
                'email' => $email,
                'error' => $e->getMessage(),
                'ip' => $request->ip()
            ]);

            return redirect()->back()
                ->with('error', 'Failed to send magic link. Please try again or use regular login.');
        }
    }

    /**
     * Verify magic link and log user in
     */
    public function verifyMagicLink(Request $request, $token)
    {
        // Rate limiting for verification attempts
        $key = 'magic-verify:' . $request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);
            return redirect()->route('login')
                ->with('error', "Too many verification attempts. Please try again in {$seconds} seconds.");
        }

        // Get token data from cache
        $tokenData = cache()->get("magic-link:{$token}");

        if (!$tokenData) {
            RateLimiter::hit($key, 300);
            return redirect()->route('login')
                ->with('error', 'Invalid or expired magic link. Please request a new one.');
        }

        // Check if token is expired
        if (Carbon::now()->gt($tokenData['expires_at'])) {
            cache()->forget("magic-link:{$token}");
            return redirect()->route('login')
                ->with('error', 'Magic link has expired. Please request a new one.');
        }

        // Find user
        $user = User::where('email', $tokenData['email'])->first();

        if (!$user) {
            RateLimiter::hit($key, 300);
            return redirect()->route('login')
                ->with('error', 'User not found. Please contact support.');
        }

        // Log the user in
        Auth::login($user, true);

        // Clear the used token
        cache()->forget("magic-link:{$token}");

        // Log successful passwordless login
        Log::info('Passwordless login successful', [
            'user_id' => $user->id,
            'email' => $user->email,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'login_method' => 'magic_link'
        ]);

        return redirect()->intended('/')
            ->with('success', 'Welcome back! You have been logged in successfully.');
    }

    /**
     * Show passwordless registration form
     */
    public function showRegisterForm()
    {
        return view('auth.passwordless.register');
    }

    /**
     * Send magic link for new user registration
     */
    public function sendRegistrationLink(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255|min:2',
            'email' => 'required|email|max:255|unique:restaurant_users,email',
        ], [
            'name.required' => 'Name is required.',
            'name.min' => 'Name must be at least 2 characters.',
            'email.required' => 'Email address is required.',
            'email.email' => 'Please enter a valid email address.',
            'email.unique' => 'This email address is already registered.',
        ]);

        $name = $request->name;
        $email = $request->email;
        $key = 'magic-register:' . $email;

        // Rate limiting: max 3 attempts per 5 minutes
        if (RateLimiter::tooManyAttempts($key, 3)) {
            $seconds = RateLimiter::availableIn($key);
            return redirect()->back()
                ->with('error', "Too many requests. Please try again in {$seconds} seconds.");
        }

        // Generate magic link token
        $token = Str::random(64);
        $expiresAt = Carbon::now()->addMinutes(30); // 30 minutes for registration

        // Store token in cache with registration data
        cache()->put("magic-register:{$token}", [
            'name' => $name,
            'email' => $email,
            'expires_at' => $expiresAt,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ], $expiresAt);

        // Generate magic link URL
        $magicLink = route('passwordless.register.verify', ['token' => $token]);

        // Send email
        try {
            Mail::send('emails.magic-register', [
                'name' => $name,
                'magicLink' => $magicLink,
                'expiresAt' => $expiresAt,
                'ip' => $request->ip(),
            ], function ($message) use ($email, $name) {
                $message->to($email)
                        ->subject('Complete Your Registration - ' . config('app.name'));
            });

            // Increment rate limiter
            RateLimiter::hit($key, 300); // 5 minutes

            Log::info('Magic registration link sent', [
                'name' => $name,
                'email' => $email,
                'ip' => $request->ip(),
                'expires_at' => $expiresAt
            ]);

            return redirect()->back()
                ->with('success', 'A registration link has been sent to your email address. Please check your inbox and click the link to complete your registration.');

        } catch (\Exception $e) {
            Log::error('Failed to send magic registration link', [
                'email' => $email,
                'error' => $e->getMessage(),
                'ip' => $request->ip()
            ]);

            return redirect()->back()
                ->with('error', 'Failed to send registration link. Please try again.');
        }
    }

    /**
     * Verify magic link and create new user
     */
    public function verifyRegistrationLink(Request $request, $token)
    {
        // Rate limiting for verification attempts
        $key = 'magic-register-verify:' . $request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);
            return redirect()->route('register')
                ->with('error', "Too many verification attempts. Please try again in {$seconds} seconds.");
        }

        // Get token data from cache
        $tokenData = cache()->get("magic-register:{$token}");

        if (!$tokenData) {
            RateLimiter::hit($key, 300);
            return redirect()->route('register')
                ->with('error', 'Invalid or expired registration link. Please request a new one.');
        }

        // Check if token is expired
        if (Carbon::now()->gt($tokenData['expires_at'])) {
            cache()->forget("magic-register:{$token}");
            return redirect()->route('register')
                ->with('error', 'Registration link has expired. Please request a new one.');
        }

        // Create new user
        $user = User::create([
            'name' => $tokenData['name'],
            'email' => $tokenData['email'],
            'password' => Hash::make(Str::random(32)), // Random password for passwordless users
            'email_verified_at' => now(), // Auto-verify email for passwordless users
        ]);

        // Log the user in
        Auth::login($user, true);

        // Clear the used token
        cache()->forget("magic-register:{$token}");

        // Log successful passwordless registration
        Log::info('Passwordless registration successful', [
            'user_id' => $user->id,
            'email' => $user->email,
            'name' => $user->name,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'registration_method' => 'magic_link'
        ]);

        return redirect()->intended('/')
            ->with('success', 'Welcome! Your account has been created and you are now logged in.');
    }
}
