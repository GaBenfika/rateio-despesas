<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Unit extends Model
{
    protected $fillable = ['company_id', 'name', 'code'];

    protected static function booted(): void
    {
        // "Unidade A" vira "unidade-a" automaticamente, se o código não for informado
        static::creating(function (Unit $unit) {
            $unit->code ??= Str::slug($unit->name);
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(ExpenseAllocation::class);
    }
}