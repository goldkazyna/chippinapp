<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\BulkStoreItemsRequest;
use App\Http\Resources\BillItemResource;
use App\Models\Bill;
use App\Services\ClaudeService;
use App\Services\WhisperService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReceiptController extends Controller
{
    public function scanReceipt(Request $request, Bill $bill, ClaudeService $claude): JsonResponse
    {
        if ($bill->user_id !== $request->user()->id) {
            return response()->json([
                'error' => 'forbidden',
                'message' => 'You do not own this bill',
            ], 403);
        }

        $request->validate([
            'image' => 'required|image|mimes:jpg,jpeg,png,heic,heif|max:10240',
        ]);

        try {
            $lang = $request->input('lang', 'en');
            $defaultCurrency = $bill->currency ?? 'USD';
            $result = $claude->scanReceipt($request->file('image'), $lang, $defaultCurrency);

            return response()->json([
                'data' => $result,
                'message' => 'Receipt scanned successfully. Review items before saving.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'scan_failed',
                'message' => 'Failed to scan receipt: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function parseVoice(Request $request, Bill $bill, WhisperService $whisper, ClaudeService $claude): JsonResponse
    {
        if ($bill->user_id !== $request->user()->id) {
            return response()->json([
                'error' => 'forbidden',
                'message' => 'You do not own this bill',
            ], 403);
        }

        $request->validate([
            'audio' => 'required|file|mimes:mp3,mp4,mpeg,mpga,m4a,wav,webm,ogg|max:25600',
        ]);

        try {
            $lang = $request->input('lang', 'en');
            $defaultCurrency = $bill->currency ?? 'USD';

            $text = $whisper->transcribe($request->file('audio'), $lang);

            if (empty(trim($text))) {
                return response()->json([
                    'data' => ['items' => [], 'total' => 0, 'currency' => $defaultCurrency, 'transcription' => ''],
                    'message' => 'Could not recognize speech.',
                ]);
            }

            $result = $claude->parseVoiceText($text, $lang, $defaultCurrency);
            $result['transcription'] = $text;

            return response()->json([
                'data' => $result,
                'message' => 'Voice parsed successfully. Review items before saving.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'voice_parse_failed',
                'message' => 'Failed to parse voice: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function bulkStore(BulkStoreItemsRequest $request, Bill $bill): JsonResponse
    {
        if ($bill->user_id !== $request->user()->id) {
            return response()->json([
                'error' => 'forbidden',
                'message' => 'You do not own this bill',
            ], 403);
        }

        $items = [];
        foreach ($request->items as $itemData) {
            $items[] = $bill->items()->create([
                'name' => $itemData['name'],
                'quantity' => $itemData['quantity'],
                'price_per_unit' => $itemData['price_per_unit'],
                'total' => $itemData['quantity'] * $itemData['price_per_unit'],
            ]);
        }

        $bill->recalculateTotal();

        return response()->json([
            'data' => BillItemResource::collection(collect($items)),
            'message' => 'Items saved successfully',
        ], 201);
    }
}
