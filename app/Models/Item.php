<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['company_id', 'category_id', 'name', 'brand', 'model', 'description'])]
class Item extends Model
{
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function details(): HasMany
    {
        return $this->hasMany(ItemDetail::class);
    }

    public function inventories(): HasMany
    {
        return $this->hasMany(Inventory::class);
    }

    public function circuitUsages(): HasMany
    {
        return $this->hasMany(CircuitItemUsage::class);
    }

    public function circuits(): BelongsToMany
    {
        return $this->belongsToMany(Circuit::class, 'circuit_item_usages')
            ->withPivot(['quantity_used', 'notes'])
            ->withTimestamps();
    }
}
