<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Participant extends Model
{
    use HasFactory;

    protected $fillable = [
        'bill_id',
        'name',
        'is_owner',
    ];

    protected function casts(): array
    {
        return [
            'is_owner' => 'boolean',
        ];
    }

    public function bill(): BelongsTo
    {
        return $this->belongsTo(Bill::class);
    }

    public function itemSplits(): HasMany
    {
        return $this->hasMany(ItemSplit::class);
    }
}
