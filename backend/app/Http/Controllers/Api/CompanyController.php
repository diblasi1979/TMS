<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Company\StoreCompanyRequest;
use App\Http\Requests\Company\UpdateCompanyRequest;
use App\Http\Resources\CompanyResource;
use App\Models\Company;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CompanyController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return CompanyResource::collection(
            Company::latest()->paginate(15)
        );
    }

    public function store(StoreCompanyRequest $request): JsonResponse
    {
        $company = Company::create($request->validated());

        return response()->json(new CompanyResource($company), 201);
    }

    public function show(Company $company): CompanyResource
    {
        return new CompanyResource($company);
    }

    public function update(UpdateCompanyRequest $request, Company $company): CompanyResource
    {
        $company->update($request->validated());

        return new CompanyResource($company->fresh());
    }

    public function destroy(Company $company): JsonResponse
    {
        $company->delete();

        return response()->json(['message' => 'Empresa eliminada.']);
    }
}
