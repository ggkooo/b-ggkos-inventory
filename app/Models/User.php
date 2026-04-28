<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\InventoryRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['company_id', 'inventory_role', 'uuid', 'name', 'username', 'email', 'phone', 'cpf', 'password', 'admin'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasUuids, Notifiable;

    /**
     * @return array<int, string>
     */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'admin' => 'boolean',
            'inventory_role' => InventoryRole::class,
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function createdCategories(): HasMany
    {
        return $this->hasMany(Category::class, 'company_id', 'company_id');
    }

    public function getOrCreateCompany(): Company
    {
        if ($this->company instanceof Company) {
            return $this->company;
        }

        if ($this->company_id !== null) {
            $this->load('company');

            if ($this->company instanceof Company) {
                return $this->company;
            }
        }

        $company = Company::query()->create([
            'name' => sprintf('%s-company-%d', $this->username ?: 'company', $this->id),
        ]);

        $this->forceFill([
            'company_id' => $company->id,
            'inventory_role' => ($this->inventory_role ?? InventoryRole::Owner)->value,
        ])->save();

        $this->setRelation('company', $company);

        return $company;
    }

    public function canViewInventory(): bool
    {
        if ((bool) $this->admin) {
            return true;
        }

        if ($this->inventory_role === null) {
            return true;
        }

        return in_array($this->inventory_role?->value, [InventoryRole::Owner->value, InventoryRole::Purchasing->value], true);
    }

    public function canManageInventory(): bool
    {
        if ((bool) $this->admin) {
            return true;
        }

        if ($this->inventory_role === null) {
            return true;
        }

        return $this->inventory_role === InventoryRole::Owner;
    }
}
