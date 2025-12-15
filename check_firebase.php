<?php
/**
 * Firebase Configuration Diagnostic Script
 * 
 * Run this on your live server to check Firebase configuration:
 * php check_firebase.php
 * 
 * Or access via web (temporarily):
 * Place this file in public/check_firebase.php and visit:
 * https://restaurant.jippymart.in/check_firebase.php
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== Firebase Configuration Diagnostic ===\n\n";

// Check environment variables
echo "1. Environment Variables:\n";
echo "   FIREBASE_PROJECT_ID: " . (env('FIREBASE_PROJECT_ID') ? '✓ Set' : '✗ Missing') . "\n";
echo "   FIREBASE_PRIVATE_KEY: " . (env('FIREBASE_PRIVATE_KEY') ? '✓ Set (' . strlen(env('FIREBASE_PRIVATE_KEY')) . ' chars)' : '✗ Missing') . "\n";
echo "   FIREBASE_CLIENT_EMAIL: " . (env('FIREBASE_CLIENT_EMAIL') ? '✓ Set' : '✗ Missing') . "\n";
echo "   FIREBASE_PRIVATE_KEY_ID: " . (env('FIREBASE_PRIVATE_KEY_ID') ? '✓ Set' : '○ Optional') . "\n";
echo "   FIREBASE_CLIENT_ID: " . (env('FIREBASE_CLIENT_ID') ? '✓ Set' : '○ Optional') . "\n";
echo "   FIREBASE_STORAGE_BUCKET: " . (env('FIREBASE_STORAGE_BUCKET') ? '✓ Set' : '○ Optional (will auto-generate)') . "\n\n";

// Check config values
echo "2. Config Values:\n";
echo "   project_id: " . (config('firebase.project_id') ?: '✗ Empty') . "\n";
echo "   client_email: " . (config('firebase.client_email') ?: '✗ Empty') . "\n";
echo "   private_key: " . (config('firebase.private_key') ? '✓ Set (' . strlen(config('firebase.private_key')) . ' chars)' : '✗ Empty') . "\n";
echo "   storage_bucket: " . (config('firebase.storage_bucket') ?: '○ Will auto-generate') . "\n\n";

// Check credentials file
$credentialsPath = storage_path('app/firebase/credentials.json');
echo "3. Credentials File:\n";
if (file_exists($credentialsPath)) {
    echo "   ✓ File exists: $credentialsPath\n";
    echo "   Permissions: " . substr(sprintf('%o', fileperms($credentialsPath)), -4) . "\n";
    $credentials = json_decode(file_get_contents($credentialsPath), true);
    if ($credentials) {
        echo "   ✓ Valid JSON\n";
        echo "   Project ID: " . ($credentials['project_id'] ?? '✗ Missing') . "\n";
    } else {
        echo "   ✗ Invalid JSON\n";
    }
} else {
    echo "   ○ File not found: $credentialsPath\n";
}
echo "\n";

// Try to initialize Firebase
echo "4. Firebase Initialization Test:\n";
try {
    $service = app(\App\Services\FirebaseStorageService::class);
    $status = $service->getInitializationStatus();
    
    if ($status['initialized']) {
        echo "   ✓ Firebase Storage initialized successfully!\n";
        echo "   Bucket: " . ($status['bucket'] ?: 'Not set') . "\n";
    } else {
        echo "   ✗ Firebase Storage NOT initialized\n";
        echo "   Reasons:\n";
        if (!$status['has_credentials_file'] && (!$status['has_project_id'] || !$status['has_private_key'] || !$status['has_client_email'])) {
            echo "     - Missing credentials (file or env vars)\n";
        } else {
            echo "     - Initialization failed (check Laravel logs)\n";
        }
    }
} catch (\Exception $e) {
    echo "   ✗ Error: " . $e->getMessage() . "\n";
}
echo "\n";

// Recommendations
echo "5. Recommendations:\n";
$hasEnv = env('FIREBASE_PROJECT_ID') && env('FIREBASE_PRIVATE_KEY') && env('FIREBASE_CLIENT_EMAIL');
$hasFile = file_exists($credentialsPath);

if (!$hasEnv && !$hasFile) {
    echo "   → Add Firebase credentials to .env file OR upload credentials.json\n";
    echo "   → See FIREBASE_STORAGE_LIVE_FIX.md for detailed instructions\n";
} elseif ($hasEnv) {
    echo "   → Environment variables are set\n";
    echo "   → Run: php artisan config:clear\n";
    echo "   → Check Laravel logs if still not working\n";
} elseif ($hasFile) {
    echo "   → Credentials file exists\n";
    echo "   → Check file permissions (should be 600)\n";
    echo "   → Check Laravel logs for initialization errors\n";
}

echo "\n=== End Diagnostic ===\n";


