<?php

namespace App\Http\Controllers\Api\Transport;

use App\Http\Controllers\Controller;
use App\Models\Operator;
use App\Models\Vehicle;
use Illuminate\Http\JsonResponse;

class AlertController extends Controller
{
    public function index(): JsonResponse
    {
        $threshold = now()->addDays(30);

        // Documentos de vehículos por vencer
        $vehicleAlerts = Vehicle::where('is_active', true)
            ->where(function ($q) use ($threshold) {
                $q->where('insurance_expiry', '<=', $threshold)
                  ->orWhere('technical_review_expiry', '<=', $threshold)
                  ->orWhere('circulation_permit_expiry', '<=', $threshold);
            })
            ->get(['id', 'plate', 'brand', 'model', 'insurance_expiry', 'technical_review_expiry', 'circulation_permit_expiry'])
            ->map(fn ($v) => [
                'type'    => 'vehicle',
                'id'      => $v->id,
                'label'   => "{$v->brand} {$v->model} ({$v->plate})",
                'alerts'  => $v->expiringDocuments(),
            ])
            ->filter(fn ($item) => !empty($item['alerts']))
            ->values();

        // Licencias de operadores por vencer
        $operatorAlerts = Operator::where('is_active', true)
            ->where('license_expiry', '<=', $threshold)
            ->get(['id', 'name', 'license_expiry', 'license_type'])
            ->map(fn ($o) => [
                'type'           => 'operator',
                'id'             => $o->id,
                'label'          => $o->name,
                'license_expiry' => $o->license_expiry?->toDateString(),
                'days_remaining' => max(0, (int) now()->diffInDays($o->license_expiry, false)),
                'license_type'   => $o->license_type,
            ]);

        return response()->json([
            'vehicle_alerts'  => $vehicleAlerts,
            'operator_alerts' => $operatorAlerts,
            'total'           => $vehicleAlerts->count() + $operatorAlerts->count(),
        ]);
    }
}
