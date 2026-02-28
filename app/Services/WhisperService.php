<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;

class WhisperService
{
    private string $apiKey;

    public function __construct()
    {
        $this->apiKey = config('services.openai.api_key');
    }

    public function transcribe(UploadedFile $audio, string $lang = 'en'): string
    {
        $response = Http::withToken($this->apiKey)
            ->timeout(30)
            ->attach('file', file_get_contents($audio->getRealPath()), $audio->getClientOriginalName())
            ->post('https://api.openai.com/v1/audio/transcriptions', [
                'model' => 'whisper-1',
                'language' => $lang === 'ru' ? 'ru' : 'en',
            ]);

        if (!$response->successful()) {
            throw new \Exception('Whisper API error: ' . $response->body());
        }

        return $response->json('text', '');
    }
}
