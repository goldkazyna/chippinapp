<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SetPaidByRequest;
use App\Models\Bill;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function setPaidBy(SetPaidByRequest $request, Bill $bill): JsonResponse
    {
        if ($bill->user_id !== $request->user()->id) {
            return response()->json([
                'error' => 'forbidden',
                'message' => 'You do not own this bill',
            ], 403);
        }

        $participant = $bill->participants()->find($request->participant_id);
        if (!$participant) {
            return response()->json([
                'error' => 'invalid_participant',
                'message' => 'Participant does not belong to this bill',
            ], 422);
        }

        $bill->update(['paid_by_participant_id' => $request->participant_id]);

        return response()->json([
            'data' => [
                'paid_by_participant_id' => $bill->paid_by_participant_id,
                'paid_by_name' => $participant->name,
            ],
            'message' => 'Payer set successfully',
        ]);
    }

    public function summary(Request $request, Bill $bill): JsonResponse
    {
        if ($bill->user_id !== $request->user()->id) {
            return response()->json([
                'error' => 'forbidden',
                'message' => 'You do not own this bill',
            ], 403);
        }

        $bill->load(['participants', 'items.splits', 'paidByParticipant']);

        // Calculate each participant's share from splits
        $shares = [];
        foreach ($bill->participants as $participant) {
            $shares[$participant->id] = [
                'participant_id' => $participant->id,
                'name' => $participant->name,
                'amount' => 0,
            ];
        }

        foreach ($bill->items as $item) {
            foreach ($item->splits as $split) {
                if (isset($shares[$split->participant_id])) {
                    $shares[$split->participant_id]['amount'] += $split->amount;
                }
            }
        }

        // Round amounts (without reference to avoid PHP foreach bug)
        foreach ($shares as $key => $share) {
            $shares[$key]['amount'] = round($share['amount'], 2);
        }

        // Calculate debts (only if payer is set)
        $payerName = null;
        $debts = [];

        if ($bill->paid_by_participant_id && $bill->paidByParticipant) {
            $payerId = (int) $bill->paid_by_participant_id;
            $payerName = $bill->paidByParticipant->name;

            foreach ($shares as $share) {
                if ($share['participant_id'] === $payerId) {
                    continue;
                }
                if ($share['amount'] > 0) {
                    $debts[] = [
                        'from' => $share['name'],
                        'to' => $payerName,
                        'amount' => $share['amount'],
                    ];
                }
            }
        }

        return response()->json([
            'data' => [
                'payer' => $payerName,
                'total' => $bill->total,
                'shares' => array_values($shares),
                'debts' => $debts,
            ],
            'message' => $payerName
                ? 'Summary calculated successfully'
                : 'Summary calculated without payer — set paid_by to see debts',
        ]);
    }
}
