<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryEvent extends Model
{
    use HasFactory;

    const EVENT_TYPES = ['arrived', 'delivered', 'failed', 'retry_scheduled'];

    protected $fillable = [
        'delivery_order_id',
        'route_id',
        'event_type',
        'occurred_at',
        'lat',
        'lng',
        'notes',
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
        'lat'         => 'float',
        'lng'         => 'float',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(DeliveryOrder::class, 'delivery_order_id');
    }

    public function route(): BelongsTo
    {
        return $this->belongsTo(DeliveryRoute::class, 'route_id');
    }
}
