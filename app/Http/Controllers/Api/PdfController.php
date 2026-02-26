<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Bill;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

class PdfController extends Controller
{
    public function generate(Request $request, Bill $bill)
    {
        // Auth: support both Authorization header and ?token= query parameter
        $user = $request->user();

        if (!$user && $request->query('token')) {
            $accessToken = PersonalAccessToken::findToken($request->query('token'));
            if ($accessToken) {
                $user = $accessToken->tokenable;
            }
        }

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        if ($bill->user_id !== $user->id) {
            return response()->json([
                'error' => 'forbidden',
                'message' => 'You do not own this bill',
            ], 403);
        }

        $bill->load(['participants', 'items.splits.participant', 'paidByParticipant']);

        // Calculate shares
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

        foreach ($shares as $key => $share) {
            $shares[$key]['amount'] = round($share['amount'], 2);
        }

        // Calculate debts
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

        $pdf = Pdf::loadView('pdf.bill', [
            'bill' => $bill,
            'shares' => array_values($shares),
            'debts' => $debts,
        ]);

        $datePart = $bill->date ? $bill->date->format('Y-m-d') : now()->format('Y-m-d');
        $filename = 'chippin_' . str_replace(' ', '_', $bill->name) . '_' . $datePart . '.pdf';

        return $pdf->download($filename);
    }
}
