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

        $bill->load(['participants', 'items.splits', 'paidByParticipant', 'adjustments']);

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

        // Apply adjustments to shares
        $adjustments = $bill->adjustments;
        $subtotal = $bill->items->sum('total');
        $participantCount = $bill->participants->count();

        $adjustmentTotal = 0;
        foreach ($adjustments as $adj) {
            $sign = $adj->type === 'discount' ? -1 : 1;
            $adjustmentTotal += $adj->amount * $sign;

            $allocatedAdj = 0;
            $participants = $bill->participants->values();
            $lastIdx = $participants->count() - 1;

            foreach ($participants as $idx => $participant) {
                if ($adj->split_mode === 'equal') {
                    if ($idx === $lastIdx) {
                        $adjShare = round($adj->amount - $allocatedAdj, 2);
                    } else {
                        $adjShare = round($adj->amount / $participantCount, 2);
                        $allocatedAdj += $adjShare;
                    }
                } else {
                    // proportional to their base share
                    $baseShare = $shares[$participant->id]['amount'] ?? 0;
                    if ($idx === $lastIdx) {
                        $adjShare = round($adj->amount - $allocatedAdj, 2);
                    } else {
                        $adjShare = $subtotal > 0
                            ? round($adj->amount * ($baseShare / $subtotal), 2)
                            : 0;
                        $allocatedAdj += $adjShare;
                    }
                }
                $shares[$participant->id]['amount'] += $adjShare * $sign;
            }
        }

        // Re-round after adjustments
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
                'total' => round($subtotal + $adjustmentTotal, 2),
                'subtotal' => $subtotal,
                'adjustments' => $adjustments->map(fn($a) => [
                    'type' => $a->type,
                    'calc_mode' => $a->calc_mode,
                    'value' => $a->value,
                    'amount' => $a->amount,
                    'split_mode' => $a->split_mode,
                ]),
                'shares' => array_values($shares),
                'debts' => $debts,
            ],
            'message' => $payerName
                ? 'Summary calculated successfully'
                : 'Summary calculated without payer — set paid_by to see debts',
        ]);
    }
}
