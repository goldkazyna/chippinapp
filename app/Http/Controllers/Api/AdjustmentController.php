<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SyncAdjustmentsRequest;
use App\Models\Bill;
use Illuminate\Http\JsonResponse;

class AdjustmentController extends Controller
{
    public function sync(SyncAdjustmentsRequest $request, Bill $bill): JsonResponse
    {
        if ($bill->user_id !== $request->user()->id) {
            return response()->json([
                'error' => 'forbidden',
                'message' => 'You do not own this bill',
            ], 403);
        }

        $subtotal = $bill->items()->sum('total');

        // Delete existing adjustments and recreate
        $bill->adjustments()->delete();

        $created = [];
        foreach ($request->adjustments as $adj) {
            $amount = $adj['calc_mode'] === 'percent'
                ? round($subtotal * $adj['value'] / 100, 2)
                : round((float) $adj['value'], 2);

            $created[] = $bill->adjustments()->create([
                'type' => $adj['type'],
                'calc_mode' => $adj['calc_mode'],
                'value' => $adj['value'],
                'amount' => $amount,
                'split_mode' => $adj['split_mode'],
            ]);
        }

        return response()->json([
            'data' => $created,
            'message' => 'Adjustments saved',
        ]);
    }
}
