<?php

namespace App\Services\Push;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Minimal Firebase Cloud Messaging (HTTP v1) client.
 *
 * Authenticates with a Google service account JSON file (FCM_CREDENTIALS):
 * a self-signed RS256 JWT is exchanged for an OAuth access token, cached for
 * ~55 minutes. Push is simply disabled while the file is not configured.
 */
class FcmClient
{
    private const SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';

    public function isConfigured(): bool
    {
        $path = config('services.fcm.credentials');

        return is_string($path) && $path !== '' && is_file($path);
    }

    /**
     * @param  array{title: string, body: string, data?: array<string, scalar|null>}  $message
     * @return bool false when the token is no longer valid (app uninstalled, ...)
     */
    public function send(string $token, array $message): bool
    {
        $credentials = $this->credentials();
        $projectId = config('services.fcm.project_id') ?: $credentials['project_id'];

        $response = Http::withToken($this->accessToken($credentials))
            ->acceptJson()
            ->timeout(10)
            ->post("https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send", [
                'message' => [
                    'token' => $token,
                    'notification' => ['title' => $message['title'], 'body' => $message['body']],
                    // FCM data values must be strings.
                    'data' => array_map(fn ($v) => (string) $v, array_filter($message['data'] ?? [], fn ($v) => $v !== null)),
                    'android' => [
                        'priority' => 'high',
                        'notification' => ['channel_id' => 'mishwar_default', 'sound' => 'default'],
                    ],
                ],
            ]);

        if ($response->successful()) {
            return true;
        }

        $status = $response->json('error.status');
        if ($response->status() === 404 || in_array($status, ['NOT_FOUND', 'UNREGISTERED', 'INVALID_ARGUMENT'], true)) {
            return false;
        }

        Log::warning('FCM send failed', ['status' => $response->status(), 'body' => $response->json()]);

        return true;
    }

    private function accessToken(array $credentials): string
    {
        return Cache::remember('fcm.access_token', now()->addMinutes(55), function () use ($credentials) {
            $response = Http::asForm()->timeout(10)->post($credentials['token_uri'], [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $this->jwt($credentials),
            ]);

            return $response->throw()->json('access_token');
        });
    }

    private function jwt(array $credentials): string
    {
        $now = time();
        $input = $this->base64Url(json_encode(['alg' => 'RS256', 'typ' => 'JWT']))
            .'.'.$this->base64Url(json_encode([
                'iss' => $credentials['client_email'],
                'scope' => self::SCOPE,
                'aud' => $credentials['token_uri'],
                'iat' => $now,
                'exp' => $now + 3600,
            ]));

        if (! openssl_sign($input, $signature, $credentials['private_key'], 'sha256WithRSAEncryption')) {
            throw new RuntimeException('Unable to sign the FCM JWT.');
        }

        return $input.'.'.$this->base64Url($signature);
    }

    private function credentials(): array
    {
        $credentials = json_decode((string) file_get_contents(config('services.fcm.credentials')), true);

        if (! is_array($credentials) || empty($credentials['client_email']) || empty($credentials['private_key'])) {
            throw new RuntimeException('Invalid FCM service account file.');
        }

        return $credentials + ['token_uri' => 'https://oauth2.googleapis.com/token', 'project_id' => null];
    }

    private function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
