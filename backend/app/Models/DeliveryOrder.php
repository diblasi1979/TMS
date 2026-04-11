<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DeliveryOrder extends Model
{
    use HasFactory;

    const STATUSES = ['pending', 'sent', 'scheduled', 'in_transit', 'delivered', 'failed', 'cancelled'];

    protected $fillable = [
        'company_id',
        'client_id',
        'reference_number',
        'description',
        'weight_kg',
        'volume_m3',
        'delivery_address',
        'delivery_lat',
        'delivery_lng',
        'contact_name',
        'contact_phone',
        'requested_date',
        'status',
        'route_id',
        'sort_order',
        'notes',
        'optimizer_exported_at',
        'optimizer_external_id',
        'optimizer_last_payload',
        'optimizer_last_response',
        'optimizer_last_error',
    ];

    protected $attributes = [
        'status' => 'pending',
    ];

    protected $casts = [
        'requested_date' => 'date',
        'delivery_lat'   => 'float',
        'delivery_lng'   => 'float',
        'weight_kg'      => 'float',
        'volume_m3'      => 'float',
        'optimizer_exported_at' => 'datetime',
        'optimizer_last_payload' => 'array',
        'optimizer_last_response' => 'array',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function route(): BelongsTo
    {
        return $this->belongsTo(DeliveryRoute::class, 'route_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(DeliveryEvent::class)->latest('occurred_at');
    }

    public function isPending(): bool    { return $this->status === 'pending'; }
    public function isSent(): bool       { return $this->status === 'sent'; }
    public function isScheduled(): bool  { return $this->status === 'scheduled'; }
    public function isInTransit(): bool  { return $this->status === 'in_transit'; }
    public function isDelivered(): bool  { return $this->status === 'delivered'; }
    public function isFailed(): bool     { return $this->status === 'failed'; }
    public function isCancelled(): bool  { return $this->status === 'cancelled'; }

    public function canBeCancelled(): bool
    {
        return in_array($this->status, ['pending', 'scheduled']);
    }

    public function canBeDeleted(): bool
    {
        return in_array($this->status, ['pending', 'cancelled']);
    }

    public function wasExportedToOptimizer(): bool
    {
        return $this->optimizer_exported_at !== null;
    }
}
