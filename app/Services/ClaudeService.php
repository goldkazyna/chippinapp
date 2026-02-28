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

    public function scanReceipt(UploadedFile $image, string $lang = 'en', string $defaultCurrency = 'USD'): array
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
                            'text' => $this->buildPrompt($lang, $defaultCurrency),
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
            return ['items' => [], 'total' => 0, 'currency' => $defaultCurrency];
        }

        if (!isset($parsed['currency']) || empty($parsed['currency'])) {
            $parsed['currency'] = $defaultCurrency;
        }

        return $parsed;
    }

    public function parseVoiceText(string $text, string $lang = 'en', string $defaultCurrency = 'USD'): array
    {
        $langInstruction = $lang === 'ru'
            ? 'Return item names in Russian.'
            : 'Return item names in English.';

        $prompt = "Parse this voice-dictated text into bill items. The user is describing items they ordered at a restaurant or store. {$langInstruction} Extract each item with name, quantity, and price_per_unit. If quantity is not mentioned, assume 1. If price is not mentioned for an item, set price_per_unit to 0. Calculate total for each item (quantity * price_per_unit). Also return the overall total and currency (use \"{$defaultCurrency}\" unless a specific currency is mentioned). Response format: {\"items\": [{\"name\": \"string\", \"quantity\": integer, \"price_per_unit\": number, \"total\": number}], \"total\": number, \"currency\": \"ISO_CODE\"}. Return ONLY valid JSON, no other text.\n\nText: \"{$text}\"";

        $response = Http::withHeaders([
            'x-api-key' => $this->apiKey,
            'anthropic-version' => '2023-06-01',
        ])->timeout(30)->post('https://api.anthropic.com/v1/messages', [
            'model' => $this->model,
            'max_tokens' => 4096,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $prompt,
                ],
            ],
        ]);

        if (!$response->successful()) {
            throw new \Exception('Claude API error: ' . $response->body());
        }

        $content = $response->json('content.0.text', '{}');

        if (preg_match('/\{[\s\S]*\}/', $content, $matches)) {
            $content = $matches[0];
        }

        $parsed = json_decode($content, true);

        if (!$parsed || !isset($parsed['items'])) {
            return ['items' => [], 'total' => 0, 'currency' => $defaultCurrency];
        }

        if (!isset($parsed['currency']) || empty($parsed['currency'])) {
            $parsed['currency'] = $defaultCurrency;
        }

        return $parsed;
    }

    private function buildPrompt(string $lang, string $defaultCurrency): string
    {
        $langInstruction = $lang === 'ru'
            ? 'Return item names in Russian. If the receipt has bilingual names (e.g. "Батат фри / Sweet potato fries"), use only the Russian version (e.g. "Батат фри").'
            : 'Return item names in English. If the receipt has bilingual names (e.g. "Батат фри / Sweet potato fries"), use only the English version (e.g. "Sweet potato fries").';

        $currencyInstruction = "Detect the currency from the receipt (look for symbols like $, €, ₽, £, ¥, ₸, د.إ or currency codes like USD, EUR, KZT, AED, RUB). Return its ISO 4217 code in the \"currency\" field. If no currency is visible on the receipt, use \"{$defaultCurrency}\".";

        return "Analyze this receipt image. {$langInstruction} {$currencyInstruction} Return JSON array of items with fields: name (string), quantity (integer), price_per_unit (number), total (number). Also return the receipt total and currency. If unreadable, return empty array. Response format: {\"items\": [...], \"total\": number, \"currency\": \"ISO_CODE\"}. Return ONLY valid JSON, no other text.";
    }
}
