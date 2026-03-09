<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BillDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'date' => $this->date->format('Y-m-d'),
            'currency' => $this->currency,
            'total' => $this->total,
            'paid_by_participant_id' => $this->paid_by_participant_id,
            'paid_by' => new ParticipantResource($this->whenLoaded('paidByParticipant')),
            'participants' => ParticipantResource::collection($this->whenLoaded('participants')),
            'items' => BillItemResource::collection($this->whenLoaded('items')),
            'adjustments' => $this->whenLoaded('adjustments'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
