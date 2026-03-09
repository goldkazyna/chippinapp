<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BillAdjustment extends Model
{
    protected $fillable = ['bill_id', 'type', 'calc_mode', 'value', 'amount', 'split_mode'];

    public function bill(): BelongsTo
    {
        return $this->belongsTo(Bill::class);
    }
}
