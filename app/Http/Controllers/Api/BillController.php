<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBillRequest;
use App\Http\Resources\BillDetailResource;
use App\Http\Resources\BillResource;
use App\Models\Bill;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BillController extends Controller
{
    public function store(StoreBillRequest $request): JsonResponse
    {
        $bill = $request->user()->bills()->create([
            'name' => $request->name,
            'date' => $request->date,
            'currency' => $request->currency ?? $request->user()->default_currency,
        ]);

        // Auto-add creator as participant with is_owner=true
        $bill->participants()->create([
            'name' => $request->user()->name,
            'is_owner' => true,
        ]);

        $bill->load('participants');

        return response()->json([
            'data' => new BillDetailResource($bill),
            'message' => 'Bill created successfully',
        ], 201);
    }

    public function index(Request $request): JsonResponse
    {
        $bills = $request->user()->bills()
            ->withCount(['participants', 'items'])
            ->orderByDesc('date')
            ->get();

        return response()->json([
            'data' => BillResource::collection($bills),
            'message' => 'Bills retrieved successfully',
        ]);
    }

    public function show(Request $request, Bill $bill): JsonResponse
    {
        if ($bill->user_id !== $request->user()->id) {
            return response()->json([
                'error' => 'forbidden',
                'message' => 'You do not own this bill',
            ], 403);
        }

        $bill->load(['participants', 'items.splits.participant', 'paidByParticipant']);

        return response()->json([
            'data' => new BillDetailResource($bill),
            'message' => 'Bill retrieved successfully',
        ]);
    }

    public function destroy(Request $request, Bill $bill): JsonResponse
    {
        if ($bill->user_id !== $request->user()->id) {
            return response()->json([
                'error' => 'forbidden',
                'message' => 'You do not own this bill',
            ], 403);
        }

        $bill->delete();

        return response()->json([
            'message' => 'Bill deleted successfully',
        ]);
    }
}
