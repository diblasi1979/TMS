<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TripPlan extends Model
{
    use HasFactory;

    public const STATUSES    = ['draft', 'confirmed', 'in_progress', 'completed', 'cancelled'];
    public const PRIORITIES  = ['low', 'normal', 'high', 'urgent'];
    public const CARGO_TYPES = ['general', 'refrigerated', 'hazardous', 'fragile', 'bulk', 'livestock', 'machinery'];

    protected $attributes = [
        'status'   => 'draft',
        'priority' => 'normal',
    ];

    protected $fillable = [
        'company_id',
        'client_id',
        'trip_number',
        'origin',
        'origin_lat',
        'origin_lng',
        'destination',
        'destination_lat',
        'destination_lng',
        'scheduled_departure',
        'scheduled_arrival',
        'actual_departure',
        'actual_arrival',
        'vehicle_id',
        'operator_id',
        'cargo_type',
        'cargo_description',
        'weight_kg',
        'volume_m3',
        'distance_km',
        'status',
        'priority',
        'notes',
    ];

    protected $casts = [
        'scheduled_departure' => 'datetime',
        'scheduled_arrival'   => 'datetime',
        'actual_departure'    => 'datetime',
        'actual_arrival'      => 'datetime',
        'weight_kg'           => 'decimal:2',
        'volume_m3'           => 'decimal:2',
        'distance_km'         => 'decimal:2',
        'origin_lat'          => 'decimal:7',
        'origin_lng'          => 'decimal:7',
        'destination_lat'     => 'decimal:7',
        'destination_lng'     => 'decimal:7',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function operator(): BelongsTo
    {
        return $this->belongsTo(Operator::class);
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isConfirmed(): bool
    {
        return $this->status === 'confirmed';
    }

    public function canBeDeleted(): bool
    {
        return $this->status === 'draft';
    }

    public function canBeCancelled(): bool
    {
        return in_array($this->status, ['draft', 'confirmed']);
    }
}
