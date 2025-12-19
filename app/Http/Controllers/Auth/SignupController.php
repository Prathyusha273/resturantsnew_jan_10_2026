<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\SetEmailData;
use App\Models\Setting;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Throwable;
use function dispatch;

class SignupController extends Controller
{
    public function show(Request $request)
    {
        $countries = $this->prepareCountries();

        $selectedCountry = $request->input('country_code');
        $phoneValue = $request->input('phone');
        $phoneNumber = $request->input('phoneNumber');

        if (!$selectedCountry) {
            [$selectedCountry, $phoneValue] = $this->extractPhonePieces($phoneNumber, $countries);
        }

        if (!$selectedCountry) {
            $selectedCountry = '91';
        }

        return view('auth.register', [
            'countries' => $countries,
            'selectedCountryCode' => $selectedCountry,
            'phone' => $phoneValue,
            'prefill' => [
                'firstName' => $request->input('firstName'),
                'lastName' => $request->input('lastName'),
                'email' => $request->input('email'),
            ],
            'loginType' => $request->input('loginType', 'email'),
            'uuid' => $request->input('uuid'),
            'photoURL' => $request->input('photoURL'),
            'createdAt' => $request->input('createdAt'),
        ]);
    }

    public function store(Request $request)
    {
        $countries = $this->prepareCountries();

        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => 'required|string|min:6',
            'country_code' => ['required', Rule::in(array_keys($countries))],
            'phone' => 'required|string|max:25',
        ]);

        $country = $countries[$validated['country_code']];
        $countryCode = '+' . $country['phoneCode'];
        $phone = preg_replace('/\D+/', '', $validated['phone']);

        if ($phone === '') {
            return back()
                ->withErrors(['phone' => __('Please enter a valid phone number')])
                ->withInput();
        }

//        $firebaseId = $request->input('uuid') ?: Str::uuid()->toString();
        $firebaseId = 'Vendor_' . Str::uuid()->toString();
        $createdAtIso = $request->input('createdAt');

        if (!$createdAtIso) {
            $createdAtIso = Carbon::now()->toIso8601String();
        }

        $autoApprove = false;
        $documentRequired = $this->isDocumentVerificationRequired();
        $isDocumentVerified = $documentRequired ? 0 : 1;

        $provider = $this->resolveProvider($request->input('loginType'));
        $photoURL = $request->input('photoURL');

        try {
            $user = DB::transaction(function () use (
                $validated,
                $countryCode,
                $phone,
                $provider,
                $createdAtIso,
                $isDocumentVerified,
                $photoURL,
                $firebaseId
            ) {
                $user = User::create([
                    'firebase_id' => $firebaseId,
//                    '_id' => $firebaseId,
                    'firstName' => $validated['first_name'],
                    'lastName' => $validated['last_name'],
                    'email' => $validated['email'],
                    'password' => Hash::make($validated['password']),
                    'countryCode' => $countryCode,
                    'phoneNumber' => $phone,
                    'role' => 'vendor',
                    'provider' => $provider,
                    'appIdentifier' => 'web',
                    'createdAt' => $createdAtIso,
                    'active' => 0,
                    'isDocumentVerify' => $isDocumentVerified,
                    'wallet_amount' => 0,
                    'vType' => 'restaurant',
                    'profilePictureURL' => $photoURL,
                ]);

                // Try to insert into restaurant_vendor_users, but don't fail if it doesn't work
                // This table is optional and shouldn't block user registration
                try {
                    $exists = DB::table('restaurant_vendor_users')
                        ->where('user_id', (string) $user->id)
                        ->exists();

                    if (!$exists) {
                        // Use raw SQL with NULL for id to handle auto-increment properly
                        DB::statement(
                            'INSERT INTO restaurant_vendor_users (id, user_id, uuid, email) VALUES (NULL, ?, ?, ?)',
                            [(string) $user->id, $user->firebase_id, $user->email]
                        );
                    } else {
                        // Update existing record
                        DB::table('restaurant_vendor_users')
                            ->where('user_id', (string) $user->id)
                            ->update([
                                'uuid' => $user->firebase_id,
                                'email' => $user->email,
                            ]);
                    }
                } catch (\Exception $e) {
                    // Log the error but don't fail the transaction - this table is optional
                    report($e);
                }

                return $user;
            });
        } catch (Throwable $exception) {
            report($exception);

            return back()
                ->withInput($request->except('password'))
                ->withErrors(['error' => __('Unable to complete signup. Please try again.')]);
        }

        $this->sendAdminNotification($user, $countryCode . $phone);

        return redirect()
            ->route('login')
            ->with('success', __('Signup submitted successfully. Please wait for admin approval.'));
    }

    protected function prepareCountries(): array
    {
        $path = public_path('countriesdata.json');

        if (!file_exists($path)) {
            return [];
        }

        $content = file_get_contents($path) ?: '';
        $decoded = json_decode($content, true) ?? [];
        $countries = [];

        foreach ($decoded as $country) {
            $phoneCode = (string) ($country['phoneCode'] ?? '');
            if ($phoneCode === '') {
                continue;
            }

            $countries[$phoneCode] = [
                'phoneCode' => $country['phoneCode'],
                'countryName' => $country['countryName'] ?? $country['name'] ?? '',
                'code' => $country['code'] ?? '',
            ];
        }

        return $countries;
    }

    protected function extractPhonePieces(?string $phoneNumber, array $countries): array
    {
        if (!$phoneNumber) {
            return [null, ''];
        }

        foreach ($countries as $code => $country) {
            $prefix = '+' . $country['phoneCode'];
            if (Str::startsWith($phoneNumber, $prefix)) {
                return [$code, substr($phoneNumber, strlen($prefix))];
            }
        }

        return [null, $phoneNumber];
    }

    protected function isDocumentVerificationRequired(): bool
    {
        $documentSettings = Setting::getFields('document_verification_settings');

        return (bool) ($documentSettings['isRestaurantVerification'] ?? false);
    }

    protected function resolveProvider(?string $loginType): string
    {
        if (!$loginType) {
            return 'email';
        }

        return strtolower($loginType);
    }

    protected function sendAdminNotification(User $user, string $fullPhone): void
    {
        dispatch(function () use ($user, $fullPhone) {
            try {
                $emailSettings = Setting::getFields('emailSetting');
                $adminEmail = $emailSettings['userName'] ?? null;

                if (!$adminEmail) {
                    return;
                }

                $template = DB::table('email_templates')
                    ->where('type', 'new_vendor_signup')
                    ->first();

                if (!$template) {
                    return;
                }

                $message = str_replace(
                    ['{userid}', '{username}', '{useremail}', '{userphone}', '{date}'],
                    [
                        $user->firebase_id,
                        trim(($user->firstName ?? '') . ' ' . ($user->lastName ?? '')),
                        $user->email,
                        $fullPhone,
                        Carbon::now()->format('d/m/Y'),
                    ],
                    $template->message
                );

//                Mail::to([$adminEmail])->send(new SetEmailData($template->subject, $message, $user->firstName ?? ''));
            } catch (\Throwable $e) {
                report($e);
            }
        })->afterResponse();
    }
}

