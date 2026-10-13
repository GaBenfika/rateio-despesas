<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExpenseAllocation extends Model
{
    protected $fillable = ['expense_id', 'unit_id', 'percentage_bp', 'amount_brl_cents'];

    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }
}