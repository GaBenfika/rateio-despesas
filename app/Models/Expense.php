<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Expense extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_CONVERTED = 'converted';

    protected $fillable = [
        'description', 'supplier', 'expense_date', 'currency', 'amount_cents',
        'exchange_rate', 'exchange_rate_date', 'amount_brl_cents', 'status',
    ];

    protected function casts(): array
    {
        return [
            'expense_date' => 'date',
            'exchange_rate_date' => 'date',
            'exchange_rate' => 'decimal:6',
        ];
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(ExpenseAllocation::class);
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }
}