<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateCountryRequest;
use App\Http\Requests\UpdateCountryRequest;
use App\Models\Country;
use App\Traits\ActivityLogTrait;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class CountryController extends Controller implements HasMiddleware
{
    use ActivityLogTrait;

    /**
     * Define the middleware for this controller.
     */
    public static function middleware(): array
    {
        return [
            new Middleware('permission:Country Index', ['only' => ['index', 'show']]),
            new Middleware('permission:Country Create', ['only' => ['store']]),
            new Middleware('permission:Country Update', ['only' => ['update']]),
            new Middleware('permission:Country Delete', ['only' => ['destroy']]),
            new Middleware('permission:Country Toggle Status', ['only' => ['toggleStatus']]),
        ];
    }

    /**
     * Display a listing of countries.
     */
    public function index(Request $request)
    {
        try {
            $perPage = $request->get('per_page', 15);
            $query = Country::query();

            // Apply Search Scope if search parameter is present
            if ($request->has('search') && $request->search != '') {
                $query->search($request->search);
            }

            $countries = $query->orderBy('name', 'asc')->paginate($perPage);

            return response()->json([
                'status' => 'success',
                'message' => 'Countries retrieved successfully',
                'data' => $countries,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve countries',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Store a newly created country in storage.
     */
    public function store(CreateCountryRequest $request)
    {
        try {
            $data = $request->validated();
            $country = Country::create($data);

            $this->logActivity('CREATE', 'Country', "Created country: {$country->name}", $data);

            return response()->json([
                'status' => 'success',
                'message' => 'Country created successfully',
                'data' => $country,
            ], 201);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create country',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Display the specified country.
     */
    public function show(string $id)
    {
        try {
            $country = Country::find($id);

            if (! $country) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Country not found',
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Country retrieved successfully',
                'data' => $country,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve country',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Update the specified country in storage.
     */
    public function update(UpdateCountryRequest $request, string $id)
    {
        try {
            $country = Country::find($id);

            if (! $country) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Country not found',
                ], 404);
            }

            $data = $request->validated();
            $country->update($data);

            $this->logActivity('UPDATE', 'Country', "Updated country: {$country->name}", $data);

            return response()->json([
                'status' => 'success',
                'message' => 'Country updated successfully',
                'data' => $country,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update country',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Remove the specified country from storage.
     */
    public function destroy(string $id)
    {
        try {
            $country = Country::find($id);

            if (! $country) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Country not found',
                ], 404);
            }

            $countryName = $country->name;
            $country->delete();

            $this->logActivity('DELETE', 'Country', "Deleted country: {$countryName}");

            return response()->json([
                'status' => 'success',
                'message' => 'Country deleted successfully',
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete country',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Get a list of all active countries (lightweight list).
     */
    public function getActiveList()
    {
        try {
            $countries = Country::active()->orderBy('name', 'asc')->get(['id', 'name', 'code']);

            if ($countries->isEmpty()) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'No active countries found',
                    'data' => [],
                ], 200);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Active countries retrieved successfully',
                'data' => $countries,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve active countries',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    public function toggleStatus(string $id)
    {
        try {
            $country = Country::find($id);

            if (!$country) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Country not found'
                ], 404);
            }

            $country->is_active = !$country->is_active;
            $country->save();

            $this->logActivity('TOGGLE_STATUS', 'Country', "Toggled country status: {$country->name} (" . ($country->is_active ? 'Active' : 'Inactive') . ")");

            return response()->json([
                'status' => 'success',
                'message' => 'Country status updated successfully',
                'data' => [
                    'id' => $country->id,
                    'is_active' => $country->is_active
                ]
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to toggle country status',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error'
            ], 500);
        }
    }
}
