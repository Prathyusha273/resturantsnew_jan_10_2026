<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Firebase\Auth\Token\Verifier;
use Firebase\Auth\Token\Exception\InvalidToken;
use Illuminate\Support\Facades\Log;

class ImpersonationController extends Controller
{
    /**
     * Check if there's an active impersonation session
     */
    public function checkImpersonation(Request $request)
    {
        try {
            // Check for impersonation key in URL parameters
            $impersonationKey = $request->get('impersonation_key');
            
            Log::info('Checking impersonation for key: ' . $impersonationKey);
            
            if ($impersonationKey) {
                // Retrieve impersonation data from cache
                $impersonationData = \Illuminate\Support\Facades\Cache::get($impersonationKey);
                
                Log::info('Cache data for key ' . $impersonationKey . ': ' . json_encode($impersonationData));
                
                if ($impersonationData) {
                    // Check if token is not expired
                    if (isset($impersonationData['expires_at']) && time() > $impersonationData['expires_at']) {
                        // Token expired, clear cache
                        \Illuminate\Support\Facades\Cache::forget($impersonationKey);
                        Log::info('Impersonation token expired for key: ' . $impersonationKey);
                        return response()->json(['has_impersonation' => false]);
                    }
                    
                    // Check if we have the required data structure
                    if (!isset($impersonationData['restaurant_uid']) || !isset($impersonationData['token'])) {
                        Log::warning('Invalid impersonation data structure for key: ' . $impersonationKey);
                        \Illuminate\Support\Facades\Cache::forget($impersonationKey);
                        return response()->json(['has_impersonation' => false]);
                    }
                    
                    try {
                        // Verify the token
                        $verifier = app(Verifier::class);
                        $verifiedToken = $verifier->verifyIdToken($impersonationData['token']);
                        
                        // Check if token is for the correct restaurant
                        if ($verifiedToken->getClaim('uid') === $impersonationData['restaurant_uid']) {
                            return response()->json([
                                'has_impersonation' => true,
                                'restaurant_uid' => $impersonationData['restaurant_uid'],
                                'restaurant_name' => $impersonationData['restaurant_name'] ?? 'Unknown Restaurant',
                                'token' => $impersonationData['token'],
                                'cache_key' => $impersonationKey
                            ]);
                        } else {
                            Log::warning('Token UID mismatch for key: ' . $impersonationKey);
                        }
                    } catch (InvalidToken $e) {
                        Log::warning('Invalid impersonation token: ' . $e->getMessage());
                        // Token is invalid, clear cache
                        \Illuminate\Support\Facades\Cache::forget($impersonationKey);
                    }
                } else {
                    Log::info('No impersonation data found in cache for key: ' . $impersonationKey);
                }
            }
            
            return response()->json(['has_impersonation' => false]);
            
        } catch (\Exception $e) {
            Log::error('Error checking impersonation: ' . $e->getMessage());
            return response()->json(['has_impersonation' => false]);
        }
    }
    
    /**
     * Process the impersonation and log in the user
     */
    public function processImpersonation(Request $request)
    {
        try {
            $cacheKey = $request->input('cache_key');
            
            if ($cacheKey) {
                // Retrieve impersonation data from cache
                $impersonationData = \Illuminate\Support\Facades\Cache::get($cacheKey);
                
                if ($impersonationData) {
                    // Check if token is not expired
                    if (time() > $impersonationData['expires_at']) {
                        \Illuminate\Support\Facades\Cache::forget($cacheKey);
                        return response()->json([
                            'success' => false,
                            'message' => 'Impersonation token has expired'
                        ], 400);
                    }
                    
                    try {
                        // Verify and use the token
                        $verifier = app(Verifier::class);
                        $verifiedToken = $verifier->verifyIdToken($impersonationData['token']);
                        
                        if ($verifiedToken->getClaim('uid') === $impersonationData['restaurant_uid']) {
                            // Clear the cache
                            \Illuminate\Support\Facades\Cache::forget($cacheKey);
                            
                            // Set impersonation flag in session
                            session([
                                'is_impersonated' => true,
                                'impersonated_restaurant_uid' => $impersonationData['restaurant_uid'],
                                'impersonated_restaurant_name' => $impersonationData['restaurant_name'],
                                'impersonated_at' => time()
                            ]);
                            
                            Log::info("Admin impersonation successful for restaurant: {$impersonationData['restaurant_name']} (UID: {$impersonationData['restaurant_uid']})");
                            
                            return response()->json([
                                'success' => true,
                                'message' => 'Impersonation successful',
                                'restaurant_name' => $impersonationData['restaurant_name'],
                                'restaurant_uid' => $impersonationData['restaurant_uid']
                            ]);
                        }
                    } catch (InvalidToken $e) {
                        Log::warning('Invalid impersonation token during processing: ' . $e->getMessage());
                        \Illuminate\Support\Facades\Cache::forget($cacheKey);
                        return response()->json([
                            'success' => false,
                            'message' => 'Invalid impersonation token'
                        ], 400);
                    }
                }
            }
            
            return response()->json([
                'success' => false,
                'message' => 'No impersonation token found'
            ], 400);
            
        } catch (\Exception $e) {
            Log::error('Error processing impersonation: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error processing impersonation'
            ], 500);
        }
    }
    
    /**
     * Clear impersonation session data
     */
    private function clearImpersonationSession()
    {
        session()->forget([
            'impersonation_token',
            'impersonation_restaurant_uid',
            'impersonation_restaurant_id',
            'impersonation_restaurant_name',
            'impersonation_timestamp',
            'impersonation_admin_id'
        ]);
    }
    
    /**
     * End impersonation session
     */
    public function endImpersonation(Request $request)
    {
        try {
            // Clear impersonation flags
            session()->forget([
                'is_impersonated',
                'impersonated_restaurant_uid',
                'impersonated_restaurant_name',
                'impersonated_at'
            ]);
            
            Log::info('Admin impersonation ended');
            
            return response()->json([
                'success' => true,
                'message' => 'Impersonation ended successfully'
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error ending impersonation: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error ending impersonation'
            ], 500);
        }
    }
    
    /**
     * Get current impersonation status
     */
    public function getImpersonationStatus(Request $request)
    {
        $isImpersonated = session('is_impersonated', false);
        
        if ($isImpersonated) {
            return response()->json([
                'is_impersonated' => true,
                'restaurant_uid' => session('impersonated_restaurant_uid'),
                'restaurant_name' => session('impersonated_restaurant_name'),
                'impersonated_at' => session('impersonated_at')
            ]);
        }
        
        return response()->json(['is_impersonated' => false]);
    }
    
    /**
     * Debug endpoint to manually store test impersonation data
     */
    public function debugStoreImpersonationData(Request $request)
    {
        try {
            $impersonationKey = $request->input('impersonation_key');
            $restaurantUid = $request->input('restaurant_uid', 'test_restaurant_uid');
            $restaurantName = $request->input('restaurant_name', 'Test Restaurant');
            $token = $request->input('token', 'test_token');
            
            if (!$impersonationKey) {
                return response()->json(['error' => 'impersonation_key is required'], 400);
            }
            
            // Store test data in cache
            $impersonationData = [
                'restaurant_uid' => $restaurantUid,
                'restaurant_name' => $restaurantName,
                'token' => $token,
                'expires_at' => time() + 300, // 5 minutes
                'created_at' => time()
            ];
            
            // Store test data in cache
            $stored = \Illuminate\Support\Facades\Cache::put($impersonationKey, $impersonationData, 300);
            
            // Verify the data was stored
            $verification = \Illuminate\Support\Facades\Cache::get($impersonationKey);
            
            if (!$stored || !$verification) {
                Log::error('Debug: Failed to store impersonation data in cache', [
                    'impersonation_key' => $impersonationKey,
                    'cache_driver' => config('cache.default'),
                    'stored' => $stored,
                    'verification' => $verification
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to store data in cache. Cache driver: ' . config('cache.default'),
                    'cache_driver' => config('cache.default')
                ], 500);
            }
            
            Log::info('Debug: Stored test impersonation data', [
                'impersonation_key' => $impersonationKey,
                'restaurant_uid' => $restaurantUid,
                'restaurant_name' => $restaurantName,
                'cache_driver' => config('cache.default'),
                'verified' => true
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Test impersonation data stored successfully',
                'data' => $impersonationData,
                'cache_driver' => config('cache.default'),
                'verified' => true
            ]);
            
        } catch (\Exception $e) {
            Log::error('Debug: Failed to store test impersonation data', [
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to store test data: ' . $e->getMessage()
            ], 500);
        }
    }
}