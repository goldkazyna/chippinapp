<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BillItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'bill_id',
        'name',
        'quantity',
        'price_per_unit',
        'total',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'price_per_unit' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    public function bill(): BelongsTo
    {
        return $this->belongsTo(Bill::class);
    }

    public function splits(): HasMany
    {
        return $this->hasMany(ItemSplit::class);
    }
}
