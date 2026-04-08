<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaintenanceSchedule extends Model
{
    use HasFactory;

    public const TYPES    = ['preventive', 'corrective', 'predictive', 'inspection'];
    public const STATUSES = ['scheduled', 'in_progress', 'completed', 'cancelled'];

    protected $attributes = [
        'status'                  => 'scheduled',
        'estimated_duration_days' => 1,
    ];

    protected $fillable = [
        'company_id',
        'vehicle_id',
        'maintenance_type',
        'description',
        'scheduled_date',
        'estimated_duration_days',
        'actual_start',
        'actual_end',
        'workshop',
        'estimated_cost',
        'actual_cost',
        'mileage_at_service',
        'next_service_km',
        'next_service_date',
        'status',
        'notes',
    ];

    protected $casts = [
        'scheduled_date'    => 'date',
        'actual_start'      => 'date',
        'actual_end'        => 'date',
        'next_service_date' => 'date',
        'estimated_cost'    => 'decimal:2',
        'actual_cost'       => 'decimal:2',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function isScheduled(): bool
    {
        return $this->status === 'scheduled';
    }

    public function isInProgress(): bool
    {
        return $this->status === 'in_progress';
    }

    public function canBeDeleted(): bool
    {
        return $this->status === 'scheduled';
    }
}
