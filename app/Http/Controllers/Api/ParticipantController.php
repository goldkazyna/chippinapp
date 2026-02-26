<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreParticipantRequest;
use App\Http\Resources\ParticipantResource;
use App\Models\Bill;
use App\Models\Participant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ParticipantController extends Controller
{
    public function store(StoreParticipantRequest $request, Bill $bill): JsonResponse
    {
        if ($bill->user_id !== $request->user()->id) {
            return response()->json([
                'error' => 'forbidden',
                'message' => 'You do not own this bill',
            ], 403);
        }

        $participant = $bill->participants()->create([
            'name' => $request->name,
        ]);

        return response()->json([
            'data' => new ParticipantResource($participant),
            'message' => 'Participant added successfully',
        ], 201);
    }

    public function destroy(Request $request, Bill $bill, Participant $participant): JsonResponse
    {
        if ($bill->user_id !== $request->user()->id) {
            return response()->json([
                'error' => 'forbidden',
                'message' => 'You do not own this bill',
            ], 403);
        }

        if ($participant->bill_id !== $bill->id) {
            return response()->json([
                'error' => 'not_found',
                'message' => 'Participant not found in this bill',
            ], 404);
        }

        if ($participant->is_owner) {
            return response()->json([
                'error' => 'cannot_delete_owner',
                'message' => 'Cannot delete the bill owner',
            ], 422);
        }

        if ($participant->itemSplits()->exists()) {
            return response()->json([
                'error' => 'has_splits',
                'message' => 'Cannot delete participant with assigned item splits',
            ], 422);
        }

        $participant->delete();

        return response()->json([
            'message' => 'Participant removed successfully',
        ]);
    }
}
