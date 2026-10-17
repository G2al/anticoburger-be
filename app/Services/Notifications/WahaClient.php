<?php

namespace App\Services\Notifications;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

/**
 * Minimal client for the WAHA (WhatsApp HTTP API) server.
 */
class WahaClient
{
    public function isConfigured(): bool
    {
        return filled(config('waha.url')) && filled(config('waha.api_key')) && filled(config('waha.session'));
    }

    /**
     * WhatsApp chat id of an E.164 phone number, or null when the number has no WhatsApp.
     *
     * @throws RequestException
     */
    public function chatIdFor(string $phoneE164): ?string
    {
        $response = $this->request()
            ->get('/api/contacts/check-exists', [
                'phone' => ltrim($phoneE164, '+'),
                'session' => config('waha.session'),
            ])
            ->throw();

        return $response->json('numberExists') === true ? $response->json('chatId') : null;
    }

    /**
     * @throws RequestException
     */
    public function sendText(string $chatId, string $text): void
    {
        $this->request()
            ->post('/api/sendText', [
                'session' => config('waha.session'),
                'chatId' => $chatId,
                'text' => $text,
            ])
            ->throw();
    }

    private function request(): PendingRequest
    {
        return Http::baseUrl(rtrim((string) config('waha.url'), '/'))
            ->withHeaders(['X-Api-Key' => (string) config('waha.api_key')])
            ->acceptJson()
            ->timeout(config('waha.timeout'));
    }
}
