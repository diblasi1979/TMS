<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Vehicle extends Model
{
    use HasFactory;

    public const TYPES = ['truck', 'van', 'pickup', 'semi', 'refrigerated', 'tanker', 'minibus'];
    public const FUEL_TYPES = ['diesel', 'gasoline', 'electric', 'gas'];
    public const STATUSES = ['available', 'on_route', 'maintenance', 'inactive'];

    protected $attributes = [
        'status'          => 'available',
        'current_mileage' => 0,
        'is_active'       => true,
    ];

    protected $fillable = [
        'company_id',
        'plate',
        'type',
        'brand',
        'model',
        'year',
        'color',
        'payload_kg',
        'volume_m3',
        'fuel_type',
        'status',
        'current_mileage',
        'insurance_expiry',
        'technical_review_expiry',
        'circulation_permit_expiry',
        'notes',
        'is_active',
    ];

    protected $casts = [
        'is_active'                  => 'boolean',
        'insurance_expiry'           => 'date',
        'technical_review_expiry'    => 'date',
        'circulation_permit_expiry'  => 'date',
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
        return $this->status === 'available';
    }

    public function expiringDocuments(int $days = 30): array
    {
        $threshold = now()->addDays($days);
        $alerts = [];

        $docs = [
            'insurance'           => $this->insurance_expiry,
            'technical_review'    => $this->technical_review_expiry,
            'circulation_permit'  => $this->circulation_permit_expiry,
        ];

        foreach ($docs as $key => $date) {
            if ($date && $date->lte($threshold)) {
                $alerts[$key] = [
                    'expiry'         => $date->toDateString(),
                    'days_remaining' => max(0, (int) now()->diffInDays($date, false)),
                ];
            }
        }

        return $alerts;
    }
}
