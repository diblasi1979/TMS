<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DeliveryRoute extends Model
{
    use HasFactory;

    const STATUSES = ['draft', 'planned', 'in_transit', 'completed', 'cancelled'];

    protected $fillable = [
        'company_id',
        'name',
        'vehicle_id',
        'operator_id',
        'assignment_id',
        'trip_plan_id',
        'planned_date',
        'dispatched_at',
        'completed_at',
        'status',
        'total_distance_km',
        'notes',
    ];

    protected $attributes = [
        'status' => 'draft',
    ];

    protected $casts = [
        'planned_date'   => 'date',
        'dispatched_at'  => 'datetime',
        'completed_at'   => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function operator(): BelongsTo
    {
        return $this->belongsTo(Operator::class);
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(VehicleAssignment::class, 'assignment_id');
    }

    public function tripPlan(): BelongsTo
    {
        return $this->belongsTo(TripPlan::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(DeliveryOrder::class, 'route_id')->orderBy('sort_order');
    }

    public function events(): HasMany
    {
        return $this->hasMany(DeliveryEvent::class, 'route_id');
    }

    public function isDraft(): bool      { return $this->status === 'draft'; }
    public function isPlanned(): bool    { return $this->status === 'planned'; }
    public function isInTransit(): bool  { return $this->status === 'in_transit'; }
    public function isCompleted(): bool  { return $this->status === 'completed'; }
    public function isCancelled(): bool  { return $this->status === 'cancelled'; }
}
