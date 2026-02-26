<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;

class ClaudeService
{
    private string $apiKey;
    private string $model;

    public function __construct()
    {
        $this->apiKey = config('services.claude.api_key');
        $this->model = 'claude-sonnet-4-20250514';
    }

    public function scanReceipt(UploadedFile $image): array
    {
        $imageData = base64_encode(file_get_contents($image->getRealPath()));
        $mimeType = $image->getMimeType();

        // HEIC -> convert mime type for API
        if (str_contains($mimeType, 'heic') || str_contains($mimeType, 'heif')) {
            $mimeType = 'image/jpeg';
        }

        $response = Http::withHeaders([
            'x-api-key' => $this->apiKey,
            'anthropic-version' => '2023-06-01',
        ])->timeout(60)->post('https://api.anthropic.com/v1/messages', [
            'model' => $this->model,
            'max_tokens' => 4096,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => [
                        [
                            'type' => 'image',
                            'source' => [
                                'type' => 'base64',
                                'media_type' => $mimeType,
                                'data' => $imageData,
                            ],
                        ],
                        [
                            'type' => 'text',
                            'text' => 'Analyze this receipt image. Return JSON array of items with fields: name (string), quantity (integer), price_per_unit (number), total (number). Also return the receipt total. If unreadable, return empty array. Response format: {"items": [...], "total": number}. Return ONLY valid JSON, no other text.',
                        ],
                    ],
                ],
            ],
        ]);

        if (!$response->successful()) {
            throw new \Exception('Claude API error: ' . $response->body());
        }

        $content = $response->json('content.0.text', '{}');

        // Extract JSON from response (in case Claude wraps it in markdown)
        if (preg_match('/\{[\s\S]*\}/', $content, $matches)) {
            $content = $matches[0];
        }

        $parsed = json_decode($content, true);

        if (!$parsed || !isset($parsed['items'])) {
            return ['items' => [], 'total' => 0];
        }

        return $parsed;
    }
}
