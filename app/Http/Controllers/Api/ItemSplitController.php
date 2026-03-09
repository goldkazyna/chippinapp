<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SplitItemRequest;
use App\Http\Resources\BillItemResource;
use App\Models\Bill;
use App\Models\BillItem;
use Illuminate\Http\JsonResponse;

class ItemSplitController extends Controller
{
    public function split(SplitItemRequest $request, Bill $bill, BillItem $item): JsonResponse
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

        // Validate custom splits before deleting existing ones
        if ($request->type === 'custom') {
            $validationResult = $this->validateCustomSplit($bill, $item, $request->splits);
            if ($validationResult !== true) {
                return $validationResult;
            }
        }

        // Clear existing splits
        $item->splits()->delete();

        if ($request->type === 'equal') {
            $this->equalSplit($bill, $item);
        } else {
            $this->createCustomSplits($item, $request->splits);
        }

        $item->load('splits.participant');

        return response()->json([
            'data' => new BillItemResource($item),
            'message' => 'Item split successfully',
        ]);
    }

    private function equalSplit(Bill $bill, BillItem $item): void
    {
        $participants = $bill->participants;
        $count = $participants->count();
        $amountPerPerson = round($item->total / $count, 2);

        foreach ($participants as $index => $participant) {
            // Last person gets the remainder to avoid rounding issues
            $amount = ($index === $count - 1)
                ? $item->total - ($amountPerPerson * ($count - 1))
                : $amountPerPerson;

            $item->splits()->create([
                'participant_id' => $participant->id,
                'quantity' => 1,
                'amount' => $amount,
            ]);
        }
    }

    private function validateCustomSplit(Bill $bill, BillItem $item, array $splits): true|JsonResponse
    {
        $billParticipantIds = $bill->participants()->pluck('id')->toArray();
        foreach ($splits as $split) {
            if (!in_array($split['participant_id'], $billParticipantIds)) {
                return response()->json([
                    'error' => 'invalid_participant',
                    'message' => 'Participant ' . $split['participant_id'] . ' does not belong to this bill',
                ], 422);
            }
        }

        $totalSplitQty = array_sum(array_column($splits, 'quantity'));
        if (abs($totalSplitQty - $item->quantity) > 0.01) {
            return response()->json([
                'error' => 'quantity_mismatch',
                'message' => "Total split quantity ({$totalSplitQty}) must equal item quantity ({$item->quantity})",
            ], 422);
        }

        return true;
    }

    private function createCustomSplits(BillItem $item, array $splits): void
    {
        $allocatedAmount = 0;
        $lastIndex = count($splits) - 1;

        foreach ($splits as $index => $split) {
            if ($index === $lastIndex) {
                // Last person gets remainder to avoid rounding drift
                $amount = round($item->total - $allocatedAmount, 2);
            } else {
                $amount = round(($split['quantity'] / $item->quantity) * $item->total, 2);
                $allocatedAmount += $amount;
            }

            $item->splits()->create([
                'participant_id' => $split['participant_id'],
                'quantity' => $split['quantity'],
                'amount' => $amount,
            ]);
        }
    }
}
