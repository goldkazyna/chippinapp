<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\BulkStoreItemsRequest;
use App\Http\Resources\BillItemResource;
use App\Models\Bill;
use App\Services\ClaudeService;
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
            $result = $claude->scanReceipt($request->file('image'), $lang);

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
