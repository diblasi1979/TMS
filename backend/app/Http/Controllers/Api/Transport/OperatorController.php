<?php

namespace App\Http\Controllers\Api\Transport;

use App\Http\Controllers\Controller;
use App\Http\Requests\Operator\StoreOperatorRequest;
use App\Http\Requests\Operator\UpdateOperatorRequest;
use App\Http\Requests\Operator\UpdateOperatorStatusRequest;
use App\Http\Resources\OperatorResource;
use App\Models\Operator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class OperatorController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Operator::with(['company', 'activeAssignment.vehicle'])
            ->when($request->status,     fn ($q) => $q->where('status', $request->status))
            ->when($request->company_id, fn ($q) => $q->where('company_id', $request->company_id))
            ->latest();

        return OperatorResource::collection($query->paginate(15));
    }

    public function store(StoreOperatorRequest $request): JsonResponse
    {
        $operator = Operator::create($request->validated());

        return response()->json(
            new OperatorResource($operator->load('company')),
            201
        );
    }

    public function show(Operator $operator): OperatorResource
    {
        return new OperatorResource(
            $operator->load(['company', 'activeAssignment.vehicle', 'activeAssignment.assignedBy'])
        );
    }

    public function update(UpdateOperatorRequest $request, Operator $operator): OperatorResource
    {
        $operator->update($request->validated());

        return new OperatorResource($operator->fresh(['company', 'activeAssignment.vehicle']));
    }

    public function destroy(Operator $operator): JsonResponse
    {
        if ($operator->activeAssignment()->exists()) {
            return response()->json([
                'message' => 'No se puede eliminar un operador con una asignación activa.',
            ], 422);
        }

        $operator->delete();

        return response()->json(['message' => 'Operador eliminado.']);
    }

    public function updateStatus(UpdateOperatorStatusRequest $request, Operator $operator): OperatorResource
    {
        $allowed = [
            'available' => ['on_duty', 'off_duty', 'inactive'],
            'on_duty'   => ['available', 'off_duty'],
            'off_duty'  => ['available', 'inactive'],
            'inactive'  => [],
        ];

        $newStatus = $request->status;

        if (!in_array($newStatus, $allowed[$operator->status] ?? [])) {
            abort(422, "Transición de estado '{$operator->status}' → '{$newStatus}' no permitida.");
        }

        $operator->update(['status' => $newStatus]);

        return new OperatorResource($operator->fresh(['company', 'activeAssignment.vehicle']));
    }

    public function expiringLicenses(): AnonymousResourceCollection
    {
        $threshold = now()->addDays(30);

        $operators = Operator::with('company')
            ->where('is_active', true)
            ->where('license_expiry', '<=', $threshold)
            ->get();

        return OperatorResource::collection($operators);
    }
}
