<?php

namespace App\Traits;
use FCM;
use App\Models\UserToken ;
use App\Models\SiteSetting;
use LaravelFCM\Message\OptionsBuilder;
use LaravelFCM\Message\PayloadDataBuilder;
use LaravelFCM\Message\PayloadNotificationBuilder;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Http;

trait  Firebase
{
    use NotificationMessageTrait ;
    
    public function sendFcmNotification($tokens = [], $types = [] , $data = [] , $lang = 'ar')
    {
        try {
            // Static Firebase configuration matching firebase-test.blade.php
            $firebaseProjectId = 'test-33d57';
            $firebaseServiceAccount = '***REMOVED***';
            
            if (empty($firebaseProjectId)) {
                \Log::error('Firebase project ID not configured');
                return ['success' => false, 'error' => 'Firebase project ID not configured'];
            }
            
            if (empty($firebaseServiceAccount)) {
                \Log::error('Firebase service account not configured');
                return ['success' => false, 'error' => 'Firebase service account not configured'];
            }
            
            if (empty($tokens)) {
                \Log::warning('No FCM tokens provided for notification');
                return ['success' => false, 'error' => 'No tokens provided'];
            }

            // Get OAuth2 access token
            $accessToken = $this->getFirebaseAccessToken($firebaseServiceAccount);
            if (!$accessToken) {
                \Log::error('Failed to get Firebase access token');
                return ['success' => false, 'error' => 'Failed to authenticate with Firebase'];
            }

            // Prepare notification data with proper structure for FCM HTTP v1 API
            $title = $this->getTitle($data, $lang);
            $body = $this->getBody($data, $lang);
            
            $results = [];
            $successCount = 0;
            $failureCount = 0;
            
            // Send notifications individually (FCM HTTP v1 API doesn't support batch sending like legacy API)
            foreach ($tokens as $token) {
                $messageData = [
                    "message" => [
                        "token" => $token,
                        "notification" => [
                            "title" => $title,
                            "body" => $body
                        ],
                        "data" => array_merge($data, [
                            "title" => $title,
                            "body" => $body,
                            "click_action" => "FLUTTER_NOTIFICATION_CLICK"
                        ]),
                        "android" => [
                            "priority" => "high",
                            "notification" => [
                                "sound" => "default",
                                "click_action" => "FLUTTER_NOTIFICATION_CLICK"
                            ]
                        ],
                        "apns" => [
                            "payload" => [
                                "aps" => [
                                    "sound" => "default",
                                    "badge" => 1,
                                    "content-available" => 1,
                                    "mutable-content" => 1
                                ]
                            ]
                        ]
                    ]
                ];
                
                $response = Http::withHeaders([
                        'Authorization' => 'Bearer ' . $accessToken,
                        'Content-Type' => 'application/json',
                    ])
                    ->withoutVerifying()
                    ->timeout(30)
                    ->post('https://fcm.googleapis.com/v1/projects/' . $firebaseProjectId . '/messages:send', $messageData);

                if ($response->successful()) {
                    $responseData = $response->json();
                    $results[] = ['token' => $token, 'success' => true, 'message_id' => $responseData['name'] ?? null];
                    $successCount++;
                } else {
                    \Log::error('FCM HTTP Error for token: ' . substr($token, 0, 20) . '...', [
                        'code' => $response->status(),
                        'response' => $response->body(),
                    ]);
                    $results[] = ['token' => $token, 'success' => false, 'error' => 'HTTP Error: ' . $response->status(), 'response' => $response->json()];
                    $failureCount++;
                }
            }
            
            // Log the full request and response for debugging
            \Log::info('FCM Request', [
                'tokens_count' => count($tokens),
                'data' => $data,
                'notification' => ['title' => $title, 'body' => $body]
            ]);
            
            \Log::info('FCM Response Summary', [
                'success_count' => $successCount,
                'failure_count' => $failureCount,
                'total_tokens' => count($tokens)
            ]);
            
            return [
                'success' => $successCount > 0,
                'success_count' => $successCount,
                'failure_count' => $failureCount,
                'results' => $results,
                'partial_failure' => $failureCount > 0 && $successCount > 0
            ];
            
        } catch (\Exception $e) {
            \Log::error('FCM Exception: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            return ['success' => false, 'error' => 'Exception: ' . $e->getMessage()];
        }
    }
    
    /**
     * Get Firebase access token using service account credentials
     */
    private function getFirebaseAccessToken($serviceAccountJson)
    {
        try {
            $serviceAccount = json_decode($serviceAccountJson, true);
            if (!$serviceAccount) {
                \Log::error('Invalid service account JSON');
                return false;
            }
            
            // Create JWT header
            $header = [
                'alg' => 'RS256',
                'typ' => 'JWT'
            ];
            
            // Create JWT payload
            $now = time();
            $payload = [
                'iss' => $serviceAccount['client_email'],
                'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
                'aud' => 'https://oauth2.googleapis.com/token',
                'iat' => $now,
                'exp' => $now + 3600 // 1 hour
            ];
            
            // Encode header and payload
            $headerEncoded = $this->base64UrlEncode(json_encode($header));
            $payloadEncoded = $this->base64UrlEncode(json_encode($payload));
            
            // Create signature
            $signature = '';
            $signatureData = $headerEncoded . '.' . $payloadEncoded;
            
            if (!openssl_sign($signatureData, $signature, $serviceAccount['private_key'], OPENSSL_ALGO_SHA256)) {
                \Log::error('Failed to sign JWT');
                return false;
            }
            
            $signatureEncoded = $this->base64UrlEncode($signature);
            
            // Create JWT
            $jwt = $headerEncoded . '.' . $payloadEncoded . '.' . $signatureEncoded;
            
            $response = Http::asForm()
                ->timeout(30)
                ->post('https://oauth2.googleapis.com/token', [
                    'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                    'assertion' => $jwt,
                ]);

            if (!$response->successful()) {
                \Log::error('OAuth2 HTTP Error: ' . $response->status(), ['response' => $response->body()]);
                return false;
            }

            $tokenData = $response->json();
            if (!isset($tokenData['access_token'])) {
                \Log::error('No access token in OAuth2 response', ['response' => $tokenData]);
                return false;
            }
            
            return $tokenData['access_token'];
            
        } catch (\Exception $e) {
            \Log::error('OAuth2 Exception: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Base64 URL encode
     */
    private function base64UrlEncode($data)
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}

