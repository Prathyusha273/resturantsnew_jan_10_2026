<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Storage;

class FirebaseStorageService
{
    protected ?Storage $storage = null;
    protected ?string $bucket = null;

    public function __construct()
    {
        try {
            $factory = null;
            $credentialsPath = storage_path('app/firebase/credentials.json');
            
            // First, try to use credentials.json file if it exists
            if (file_exists($credentialsPath)) {
                try {
                    $factory = (new Factory)->withServiceAccount($credentialsPath);
                    Log::info('Firebase Storage initialized using credentials.json file');
                } catch (\Exception $e) {
                    Log::warning('Failed to initialize Firebase with credentials.json, trying environment variables', [
                        'error' => $e->getMessage()
                    ]);
                }
            }
            
            // If credentials file doesn't exist or failed, try environment variables
            if (!$factory) {
                $projectId = config('firebase.project_id');
                $privateKey = config('firebase.private_key');
                $clientEmail = config('firebase.client_email');
                
                // Check if required environment variables are set
                if (empty($projectId) || empty($privateKey) || empty($clientEmail)) {
                    throw new \Exception(
                        'Firebase credentials not found. ' .
                        'Please either: ' .
                        '1) Place credentials.json at storage/app/firebase/credentials.json, or ' .
                        '2) Set FIREBASE_PROJECT_ID, FIREBASE_PRIVATE_KEY, and FIREBASE_CLIENT_EMAIL in .env file'
                    );
                }
                
                $factory = (new Factory)
                    ->withServiceAccount([
                        'project_id' => $projectId,
                        'private_key_id' => config('firebase.private_key_id'),
                        'private_key' => str_replace('\\n', "\n", $privateKey),
                        'client_email' => $clientEmail,
                        'client_id' => config('firebase.client_id'),
                        'auth_uri' => 'https://accounts.google.com/o/oauth2/auth',
                        'token_uri' => 'https://oauth2.googleapis.com/token',
                        'auth_provider_x509_cert_url' => 'https://www.googleapis.com/oauth2/v1/certs',
                        'client_x509_cert_url' => config('firebase.client_x509_cert_url'),
                    ]);
                Log::info('Firebase Storage initialized using environment variables');
            }

            $this->storage = $factory->createStorage();
            
            // Get bucket name from config or use default format
            $this->bucket = config('firebase.storage_bucket');
            if (empty($this->bucket)) {
                $projectId = config('firebase.project_id');
                if (empty($projectId)) {
                    // Try to get from credentials file if available
                    $credentialsPath = storage_path('app/firebase/credentials.json');
                    if (file_exists($credentialsPath)) {
                        $credentials = json_decode(file_get_contents($credentialsPath), true);
                        $projectId = $credentials['project_id'] ?? null;
                    }
                }
                
                if ($projectId) {
                    // Try new format first (.firebasestorage.app), fallback to old format (.appspot.com)
                    $this->bucket = $projectId . '.firebasestorage.app';
                } else {
                    throw new \Exception('Firebase project_id is required to determine storage bucket');
                }
            }
            
            Log::info('Firebase Storage initialized successfully', [
                'bucket' => $this->bucket,
                'project_id' => $projectId ?? config('firebase.project_id')
            ]);
        } catch (\Exception $e) {
            $errorDetails = [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'has_credentials_file' => file_exists($credentialsPath),
                'env_project_id' => !empty(config('firebase.project_id')),
                'env_private_key' => !empty(config('firebase.private_key')),
                'env_client_email' => !empty(config('firebase.client_email')),
            ];
            
            Log::error('Firebase Storage initialization failed', $errorDetails);
            // Don't set storage to null here, let it fail gracefully
        }
    }

    /**
     * Upload a file to Firebase Storage
     *
     * @param UploadedFile $file
     * @param string $path Path in Firebase Storage (e.g., 'restaurants/photo.jpg')
     * @return string Public URL of the uploaded file
     * @throws \Exception
     */
    public function uploadFile(UploadedFile $file, string $path): string
    {
        if (!$this->storage) {
            $credentialsPath = storage_path('app/firebase/credentials.json');
            $hasCredentialsFile = file_exists($credentialsPath);
            $hasProjectId = !empty(config('firebase.project_id'));
            $hasPrivateKey = !empty(config('firebase.private_key'));
            $hasClientEmail = !empty(config('firebase.client_email'));
            
            $message = 'Firebase Storage is not initialized. ';
            $suggestions = [];
            
            if (!$hasCredentialsFile && (!$hasProjectId || !$hasPrivateKey || !$hasClientEmail)) {
                $suggestions[] = 'Missing Firebase credentials.';
                if (!$hasCredentialsFile) {
                    $suggestions[] = 'Option 1: Place credentials.json at: ' . $credentialsPath;
                }
                if (!$hasProjectId || !$hasPrivateKey || !$hasClientEmail) {
                    $suggestions[] = 'Option 2: Set FIREBASE_PROJECT_ID, FIREBASE_PRIVATE_KEY, and FIREBASE_CLIENT_EMAIL in .env file';
                }
            } else {
                $suggestions[] = 'Firebase credentials exist but initialization failed. Check Laravel logs for details.';
                $suggestions[] = 'Common issues: Invalid private key format, network connectivity, or Firebase project permissions.';
            }
            
            throw new \Exception($message . implode(' ', $suggestions));
        }

        try {
            $bucket = $this->storage->getBucket($this->bucket);
            
            // Generate unique filename if needed
            $filename = $this->generateUniqueFilename($path, $file->getClientOriginalExtension());
            
            // Upload file
            $object = $bucket->upload(
                file_get_contents($file->getRealPath()),
                [
                    'name' => $filename,
                    'metadata' => [
                        'contentType' => $file->getMimeType(),
                    ],
                ]
            );

            // Make the file publicly accessible
            try {
                // Use makePublic() method if available, otherwise try ACL update
                if (method_exists($object, 'makePublic')) {
                    $object->makePublic();
                } else {
                    $object->update(['acl' => [['entity' => 'allUsers', 'role' => 'READER']]]);
                }
                
                // Use public URL format
                $publicUrl = sprintf(
                    'https://firebasestorage.googleapis.com/v0/b/%s/o/%s?alt=media',
                    $this->bucket,
                    urlencode($filename)
                );
            } catch (\Exception $e) {
                // If making public fails, use signed URL as fallback
                Log::warning('Failed to make file public, using signed URL', ['error' => $e->getMessage()]);
                try {
                    $publicUrl = $object->signedUrl(new \DateTime('+10 years'));
                } catch (\Exception $signedException) {
                    // Last resort: construct URL manually
                    $publicUrl = sprintf(
                        'https://firebasestorage.googleapis.com/v0/b/%s/o/%s?alt=media',
                        $this->bucket,
                        urlencode($filename)
                    );
                }
            }

            Log::info('File uploaded to Firebase Storage', [
                'path' => $filename,
                'original_name' => $file->getClientOriginalName(),
                'size' => $file->getSize(),
            ]);

            return $publicUrl;
        } catch (\Exception $e) {
            Log::error('Failed to upload file to Firebase Storage', [
                'path' => $path,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw new \Exception('Failed to upload file to Firebase Storage: ' . $e->getMessage());
        }
    }

    /**
     * Delete a file from Firebase Storage
     *
     * @param string $url Public URL of the file
     * @return bool
     */
    public function deleteFile(string $url): bool
    {
        if (!$this->storage) {
            return false;
        }

        try {
            // Extract path from Firebase Storage URL
            $path = $this->extractPathFromUrl($url);
            if (!$path) {
                return false;
            }

            $bucket = $this->storage->getBucket($this->bucket);
            $object = $bucket->object($path);
            $object->delete();

            Log::info('File deleted from Firebase Storage', ['path' => $path]);
            return true;
        } catch (\Exception $e) {
            Log::warning('Failed to delete file from Firebase Storage', [
                'url' => $url,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Generate unique filename
     *
     * @param string $path
     * @param string $extension
     * @return string
     */
    protected function generateUniqueFilename(string $path, string $extension): string
    {
        $directory = dirname($path);
        $basename = basename($path, '.' . $extension);
        $timestamp = time();
        $random = bin2hex(random_bytes(4));
        
        $filename = $basename . '_' . $timestamp . '_' . $random . '.' . $extension;
        
        return $directory !== '.' ? $directory . '/' . $filename : $filename;
    }

    /**
     * Check if Firebase Storage is initialized
     *
     * @return bool
     */
    public function isInitialized(): bool
    {
        return $this->storage !== null;
    }

    /**
     * Get initialization status with details
     *
     * @return array
     */
    public function getInitializationStatus(): array
    {
        $credentialsPath = storage_path('app/firebase/credentials.json');
        
        return [
            'initialized' => $this->isInitialized(),
            'has_credentials_file' => file_exists($credentialsPath),
            'has_project_id' => !empty(config('firebase.project_id')),
            'has_private_key' => !empty(config('firebase.private_key')),
            'has_client_email' => !empty(config('firebase.client_email')),
            'bucket' => $this->bucket,
        ];
    }

    /**
     * Extract file path from Firebase Storage URL
     *
     * @param string $url
     * @return string|null
     */
    protected function extractPathFromUrl(string $url): ?string
    {
        // Handle Firebase Storage URL format: https://firebasestorage.googleapis.com/v0/b/{bucket}/o/{path}?alt=media
        if (preg_match('/firebasestorage\.googleapis\.com\/v0\/b\/[^\/]+\/o\/([^?]+)/', $url, $matches)) {
            return urldecode($matches[1]);
        }

        // Handle signed URL format
        if (preg_match('/firebasestorage\.googleapis\.com\/v0\/b\/[^\/]+\/o\/([^&]+)/', $url, $matches)) {
            return urldecode($matches[1]);
        }

        return null;
    }
}

