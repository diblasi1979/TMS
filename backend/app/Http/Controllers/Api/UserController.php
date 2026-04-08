<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class UserController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return UserResource::collection(
            User::with('company')->latest()->paginate(15)
        );
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $user = User::create($request->validated());

        return response()->json(new UserResource($user->load('company')), 201);
    }

    public function show(User $user): UserResource
    {
        return new UserResource($user->load('company'));
    }

    public function update(UpdateUserRequest $request, User $user): UserResource
    {
        $user->update($request->validated());

        return new UserResource($user->fresh(['company']));
    }

    public function destroy(User $user): JsonResponse
    {
        $user->tokens()->delete();
        $user->delete();

        return response()->json(['message' => 'Usuario eliminado.']);
    }
}
