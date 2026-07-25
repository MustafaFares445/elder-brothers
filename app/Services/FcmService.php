<?php

namespace App\Services;

use App\Models\User;
use Google\Auth\Credentials\ServiceAccountCredentials;
use Illuminate\Support\Facades\Http;

class FcmService
{
    public function sendToUser(User $user, string $title, string $body, array $data = []): void
    {
        $tokens = $user->devices()
            ->where('notifications_enabled', true)
            ->pluck('fcm_token')
            ->filter()
            ->unique();

        foreach ($tokens as $token) {
            $this->sendToToken($token, $title, $body, $data);
        }
    }

    public function sendToToken(string $token, string $title, string $body, array $data = []): void
    {
        if (config('services.fcm.driver') === 'log') {
            logger()->info('FCM notification', compact('token', 'title', 'body', 'data'));

            return;
        }

        $credentialsPath = config('services.fcm.credentials');
        $projectId = config('services.fcm.project_id');

        if (! $credentialsPath || ! $projectId) {
            throw new \RuntimeException('FCM credentials and project ID are required.');
        }

        $credentials = new ServiceAccountCredentials(
            ['https://www.googleapis.com/auth/firebase.messaging'],
            $credentialsPath,
        );

        $authToken = $credentials->fetchAuthToken();
        $accessToken = $authToken['access_token'] ?? null;

        if (! $accessToken) {
            throw new \RuntimeException('Unable to obtain an FCM access token.');
        }

        Http::withToken($accessToken)
            ->acceptJson()
            ->post("https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send", [
                'message' => [
                    'token' => $token,
                    'notification' => ['title' => $title, 'body' => $body],
                    'data' => collect($data)->map(fn ($value) => (string) $value)->all(),
                    'android' => ['priority' => 'high'],
                    'apns' => ['headers' => ['apns-priority' => '10']],
                ],
            ])
            ->throw();
    }
}
