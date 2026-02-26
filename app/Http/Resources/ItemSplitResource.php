<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ItemSplitResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'participant_id' => $this->participant_id,
            'participant_name' => $this->whenLoaded('participant', fn() => $this->participant->name),
            'quantity' => $this->quantity,
            'amount' => $this->amount,
        ];
    }
}
