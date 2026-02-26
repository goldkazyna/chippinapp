<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BillItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'quantity' => $this->quantity,
            'price_per_unit' => $this->price_per_unit,
            'total' => $this->total,
            'splits' => ItemSplitResource::collection($this->whenLoaded('splits')),
        ];
    }
}
