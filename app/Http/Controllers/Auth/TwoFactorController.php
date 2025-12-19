<?php
//
//namespace App\Http\Controllers\Auth;
//
//use App\Http\Controllers\Controller;
//use App\Models\User;
//use Illuminate\Http\Request;
//use Illuminate\Support\Facades\Auth;
//use Illuminate\Support\Facades\Hash;
//use Illuminate\Support\Facades\Log;
//use Illuminate\Support\Str;
//use PragmaRX\Google2FA\Google2FA;
//
//class TwoFactorController extends Controller
//{
//    protected $google2fa;
//
//    public function __construct()
//    {
//        $this->google2fa = new Google2FA();
//    }
//
//    /**
//     * Show the 2FA setup page
//     */
//    public function showSetupForm()
//    {
//        if (!Auth::check()) {
//            return redirect()->route('login');
//        }
//
//        $user = Auth::user();
//
//        // If user already has 2FA enabled, redirect to dashboard
//        if ($user->two_factor_secret) {
//            return redirect()->route('home')->with('info', 'Two-factor authentication is already enabled.');
//        }
//
//        // Generate a new secret key
//        $secretKey = $this->google2fa->generateSecretKey();
//
//        // Store temporarily in session for verification
//        session(['2fa_setup_secret' => $secretKey]);
//
//        // Generate QR code URL
//        $qrCodeUrl = $this->google2fa->getQRCodeUrl(
//            config('app.name'),
//            $user->email,
//            $secretKey
//        );
//
//        return view('auth.2fa.setup', compact('secretKey', 'qrCodeUrl'));
//    }
//
//    /**
//     * Enable 2FA for the user
//     */
//    public function enable(Request $request)
//    {
//        $request->validate([
//            'code' => 'required|string|size:6',
//        ], [
//            'code.required' => 'Verification code is required.',
//            'code.size' => 'Verification code must be 6 digits.',
//        ]);
//
//        $user = Auth::user();
//        $secretKey = session('2fa_setup_secret');
//
//        if (!$secretKey) {
//            return redirect()->route('2fa.setup')
//                ->with('error', 'Session expired. Please try again.');
//        }
//
//        // Verify the code
//        $valid = $this->google2fa->verifyKey($secretKey, $request->code);
//
//        if (!$valid) {
//            return redirect()->route('2fa.setup')
//                ->with('error', 'Invalid verification code. Please try again.');
//        }
//
//        // Enable 2FA for the user
//        $user->update([
//            'two_factor_secret' => encrypt($secretKey),
//            'two_factor_enabled' => true,
//        ]);
//
//        // Clear the temporary secret from session
//        session()->forget('2fa_setup_secret');
//
//        // Generate backup codes
//        $backupCodes = $this->generateBackupCodes();
//        $user->update(['two_factor_backup_codes' => encrypt(json_encode($backupCodes))]);
//
//        Log::info('2FA enabled for user', [
//            'user_id' => $user->id,
//            'email' => $user->email,
//            'ip' => $request->ip()
//        ]);
//
//        return view('auth.2fa.backup-codes', compact('backupCodes'))
//            ->with('success', 'Two-factor authentication has been enabled successfully!');
//    }
//
//    /**
//     * Show the 2FA verification form
//     */
//    public function showVerificationForm()
//    {
//        if (!session('2fa_required')) {
//            return redirect()->route('home');
//        }
//
//        return view('auth.2fa.verify');
//    }
//
//    /**
//     * Verify 2FA code
//     */
//    public function verify(Request $request)
//    {
//        $request->validate([
//            'code' => 'required|string|size:6',
//        ], [
//            'code.required' => 'Verification code is required.',
//            'code.size' => 'Verification code must be 6 digits.',
//        ]);
//
//        $user = Auth::user();
//        $secretKey = decrypt($user->two_factor_secret);
//        $code = $request->code;
//
//        // Check if it's a backup code
//        $backupCodes = json_decode(decrypt($user->two_factor_backup_codes), true);
//
//        if (in_array($code, $backupCodes)) {
//            // Remove used backup code
//            $backupCodes = array_diff($backupCodes, [$code]);
//            $user->update(['two_factor_backup_codes' => encrypt(json_encode($backupCodes))]);
//
//            Log::info('2FA backup code used', [
//                'user_id' => $user->id,
//                'email' => $user->email,
//                'ip' => $request->ip()
//            ]);
//
//            $this->complete2FAVerification();
//            return redirect()->intended('/');
//        }
//
//        // Verify TOTP code
//        $valid = $this->google2fa->verifyKey($secretKey, $code);
//
//        if (!$valid) {
//            Log::warning('Invalid 2FA code attempt', [
//                'user_id' => $user->id,
//                'email' => $user->email,
//                'ip' => $request->ip()
//            ]);
//
//            return redirect()->route('2fa.verify')
//                ->with('error', 'Invalid verification code. Please try again.');
//        }
//
//        Log::info('2FA verification successful', [
//            'user_id' => $user->id,
//            'email' => $user->email,
//            'ip' => $request->ip()
//        ]);
//
//        $this->complete2FAVerification();
//        return redirect()->intended('/');
//    }
//
//    /**
//     * Disable 2FA for the user
//     */
//    public function disable(Request $request)
//    {
//        $request->validate([
//            'password' => 'required|string',
//            'code' => 'required|string|size:6',
//        ]);
//
//        $user = Auth::user();
//
//        // Verify password
//        if (!Hash::check($request->password, $user->password)) {
//            return redirect()->back()
//                ->with('error', 'Invalid password.');
//        }
//
//        // Verify 2FA code
//        $secretKey = decrypt($user->two_factor_secret);
//        $valid = $this->google2fa->verifyKey($secretKey, $request->code);
//
//        if (!$valid) {
//            return redirect()->back()
//                ->with('error', 'Invalid verification code.');
//        }
//
//        // Disable 2FA
//        $user->update([
//            'two_factor_secret' => null,
//            'two_factor_enabled' => false,
//            'two_factor_backup_codes' => null,
//        ]);
//
//        Log::info('2FA disabled for user', [
//            'user_id' => $user->id,
//            'email' => $user->email,
//            'ip' => $request->ip()
//        ]);
//
//        return redirect()->route('profile')
//            ->with('success', 'Two-factor authentication has been disabled.');
//    }
//
//    /**
//     * Regenerate backup codes
//     */
//    public function regenerateBackupCodes(Request $request)
//    {
//        $request->validate([
//            'password' => 'required|string',
//        ]);
//
//        $user = Auth::user();
//
//        // Verify password
//        if (!Hash::check($request->password, $user->password)) {
//            return redirect()->back()
//                ->with('error', 'Invalid password.');
//        }
//
//        // Generate new backup codes
//        $backupCodes = $this->generateBackupCodes();
//        $user->update(['two_factor_backup_codes' => encrypt(json_encode($backupCodes))]);
//
//        Log::info('2FA backup codes regenerated', [
//            'user_id' => $user->id,
//            'email' => $user->email,
//            'ip' => $request->ip()
//        ]);
//
//        return view('auth.2fa.backup-codes', compact('backupCodes'))
//            ->with('success', 'New backup codes have been generated.');
//    }
//
//    /**
//     * Complete 2FA verification
//     */
//    protected function complete2FAVerification()
//    {
//        session()->forget('2fa_required');
//        session()->put('2fa_verified', true);
//    }
//
//    /**
//     * Generate backup codes
//     */
//    protected function generateBackupCodes()
//    {
//        $codes = [];
//        for ($i = 0; $i < 10; $i++) {
//            $codes[] = strtoupper(Str::random(8));
//        }
//        return $codes;
//    }
//}
