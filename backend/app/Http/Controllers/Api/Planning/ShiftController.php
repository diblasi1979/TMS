<?php

namespace App\Http\Controllers\Api\Planning;

use App\Http\Controllers\Controller;
use App\Http\Requests\Planning\StoreShiftRequest;
use App\Http\Requests\Planning\UpdateShiftRequest;
use App\Http\Resources\Planning\ShiftResource;
use App\Models\OperatorShift;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Auth;

class ShiftController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = OperatorShift::with('operator')
            ->where('company_id', Auth::user()->company_id);

        if ($request->filled('operator_id')) {
            $query->where('operator_id', $request->operator_id);
        }
        if ($request->filled('shift_type')) {
            $query->where('shift_type', $request->shift_type);
        }
        if ($request->filled('from')) {
            $query->where('start_datetime', '>=', $request->from);
        }
        if ($request->filled('to')) {
            $query->where('start_datetime', '<=', $request->to);
        }

        return ShiftResource::collection($query->orderBy('start_datetime')->paginate(20));
    }

    public function store(StoreShiftRequest $request): JsonResponse
    {
        // Verificar solapamiento con turnos del mismo tipo
        $overlap = OperatorShift::where('operator_id', $request->operator_id)
            ->where('shift_type', $request->shift_type)
            ->where('start_datetime', '<', $request->end_datetime)
            ->where('end_datetime', '>', $request->start_datetime)
            ->exists();

        if ($overlap) {
            return response()->json(['message' => 'El operador ya tiene un turno de ese tipo en ese período.'], 422);
        }

        $shift = OperatorShift::create([
            ...$request->validated(),
            'company_id' => Auth::user()->company_id,
            'created_by' => Auth::id(),
        ]);

        return response()->json(new ShiftResource($shift->load('operator')), 201);
    }

    public function show(OperatorShift $shift): ShiftResource
    {
        return new ShiftResource($shift->load('operator'));
    }

    public function update(UpdateShiftRequest $request, OperatorShift $shift): ShiftResource
    {
        $shift->update($request->validated());
        return new ShiftResource($shift->fresh()->load('operator'));
    }

    public function destroy(OperatorShift $shift): JsonResponse
    {
        $shift->delete();
        return response()->json(null, 204);
    }
}
