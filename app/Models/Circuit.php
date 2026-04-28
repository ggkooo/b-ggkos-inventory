<?php

namespace App\Models;

use Database\Factories\CircuitFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['company_id', 'name', 'description', 'location', 'assembled_at'])]
class Circuit extends Model
{
    /** @use HasFactory<CircuitFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'assembled_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function itemUsages(): HasMany
    {
        return $this->hasMany(CircuitItemUsage::class);
    }

    public function items(): BelongsToMany
    {
        return $this->belongsToMany(Item::class, 'circuit_item_usages')
            ->withPivot(['quantity_used', 'notes'])
            ->withTimestamps();
    }
}
