<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OperatorShift extends Model
{
    use HasFactory;

    public const TYPES = ['work', 'rest', 'vacation', 'leave', 'standby'];

    protected $fillable = [
        'company_id',
        'operator_id',
        'shift_type',
        'start_datetime',
        'end_datetime',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'start_datetime' => 'datetime',
        'end_datetime'   => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function operator(): BelongsTo
    {
        return $this->belongsTo(Operator::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function blocksAssignment(): bool
    {
        return in_array($this->shift_type, ['rest', 'vacation', 'leave']);
    }
}
