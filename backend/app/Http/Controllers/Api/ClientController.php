<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Client\StoreClientRequest;
use App\Http\Requests\Client\UpdateClientRequest;
use App\Http\Resources\ClientResource;
use App\Models\Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ClientController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return ClientResource::collection(
            Client::with('company')->latest()->paginate(15)
        );
    }

    public function store(StoreClientRequest $request): JsonResponse
    {
        $client = Client::create($request->validated());

        return response()->json(new ClientResource($client->load('company')), 201);
    }

    public function show(Client $client): ClientResource
    {
        return new ClientResource($client->load('company'));
    }

    public function update(UpdateClientRequest $request, Client $client): ClientResource
    {
        $client->update($request->validated());

        return new ClientResource($client->fresh(['company']));
    }

    public function destroy(Client $client): JsonResponse
    {
        $client->delete();

        return response()->json(['message' => 'Cliente eliminado.']);
    }
}
