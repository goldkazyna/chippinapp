<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BillResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'date' => $this->date->format('Y-m-d'),
            'currency' => $this->currency,
            'total' => $this->total,
            'participants_count' => $this->whenCounted('participants'),
            'items_count' => $this->whenCounted('items'),
            'created_at' => $this->created_at,
        ];
    }
}
