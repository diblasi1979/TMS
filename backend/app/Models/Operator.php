<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Operator extends Model
{
    use HasFactory;

    public const LICENSE_TYPES = ['A1', 'A2', 'B', 'C', 'D', 'E'];
    public const STATUSES      = ['available', 'on_duty', 'off_duty', 'inactive'];

    protected $attributes = [
        'status'    => 'available',
        'is_active' => true,
    ];

    protected $fillable = [
        'company_id',
        'name',
        'document_number',
        'email',
        'phone',
        'address',
        'license_number',
        'license_type',
        'license_expiry',
        'emergency_contact',
        'emergency_phone',
        'status',
        'notes',
        'is_active',
    ];

    protected $casts = [
        'is_active'      => 'boolean',
        'license_expiry' => 'date',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(VehicleAssignment::class);
    }

    public function activeAssignment(): HasOne
    {
        return $this->hasOne(VehicleAssignment::class)->whereNull('released_at');
    }

    public function isAvailable(): bool
    {
        return $this->status === 'available' && $this->is_active;
    }

    public function isLicenseExpiringSoon(int $days = 30): bool
    {
        return $this->license_expiry && $this->license_expiry->lte(now()->addDays($days));
    }
}
