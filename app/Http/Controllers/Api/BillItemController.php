<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBillItemRequest;
use App\Http\Requests\UpdateBillItemRequest;
use App\Http\Resources\BillItemResource;
use App\Models\Bill;
use App\Models\BillItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BillItemController extends Controller
{
    public function store(StoreBillItemRequest $request, Bill $bill): JsonResponse
    {
        if ($bill->user_id !== $request->user()->id) {
            return response()->json([
                'error' => 'forbidden',
                'message' => 'You do not own this bill',
            ], 403);
        }

        $item = $bill->items()->create([
            'name' => $request->name,
            'quantity' => $request->quantity,
            'price_per_unit' => $request->price_per_unit,
            'total' => $request->quantity * $request->price_per_unit,
        ]);

        $bill->recalculateTotal();

        return response()->json([
            'data' => new BillItemResource($item),
            'message' => 'Item added successfully',
        ], 201);
    }

    public function update(UpdateBillItemRequest $request, Bill $bill, BillItem $item): JsonResponse
    {
        if ($bill->user_id !== $request->user()->id) {
            return response()->json([
                'error' => 'forbidden',
                'message' => 'You do not own this bill',
            ], 403);
        }

        if ($item->bill_id !== $bill->id) {
            return response()->json([
                'error' => 'not_found',
                'message' => 'Item not found in this bill',
            ], 404);
        }

        $item->fill($request->validated());

        $quantity = $item->quantity;
        $price = $item->price_per_unit;
        $item->total = $quantity * $price;
        $item->save();

        $bill->recalculateTotal();

        return response()->json([
            'data' => new BillItemResource($item),
            'message' => 'Item updated successfully',
        ]);
    }

    public function destroy(Request $request, Bill $bill, BillItem $item): JsonResponse
    {
        if ($bill->user_id !== $request->user()->id) {
            return response()->json([
                'error' => 'forbidden',
                'message' => 'You do not own this bill',
            ], 403);
        }

        if ($item->bill_id !== $bill->id) {
            return response()->json([
                'error' => 'not_found',
                'message' => 'Item not found in this bill',
            ], 404);
        }

        $item->delete(); // cascade deletes related splits

        $bill->recalculateTotal();

        return response()->json([
            'message' => 'Item deleted successfully',
        ]);
    }
}
