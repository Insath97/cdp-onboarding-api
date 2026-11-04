<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateDocumentTypeRequest;
use App\Http\Requests\UpdateDocumentTypeRequest;
use App\Models\DocumentType;
use App\Traits\ActivityLogTrait;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class DocumentTypeController extends Controller implements HasMiddleware
{
    use ActivityLogTrait;

    /**
     * Define the middleware for this controller.
     */
    public static function middleware(): array
    {
        return [
            new Middleware('permission:Document Type Index', ['index', 'show']),
            new Middleware('permission:Document Type Create', ['store']),
            new Middleware('permission:Document Type Update', ['update']),
            new Middleware('permission:Document Type Delete', ['destroy']),
            new Middleware('permission:Document Type Toggle Status', ['toggleStatus']),
        ];
    }

    /**
     * Display a listing of document types.
     */
    public function index(Request $request)
    {
        try {
            $perPage = $request->get('per_page', 15);
            $query = DocumentType::query();

            // Apply Search Scope if search parameter is present
            if ($request->has('search') && $request->search != '') {
                $query->search($request->search);
            }

            if ($request->has('is_active')) {
                $query->where('is_active', $request->boolean('is_active'));
            }

            if ($request->has('is_required')) {
                $query->where('is_required', $request->boolean('is_required'));
            }

            $documentTypes = $query->orderBy('document_name', 'asc')->paginate($perPage);

            return response()->json([
                'status' => 'success',
                'message' => 'Document types retrieved successfully',
                'data' => $documentTypes,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve document types',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Store a newly created document type in storage.
     */
    public function store(CreateDocumentTypeRequest $request)
    {
        try {
            $data = $request->validated();
            $documentType = DocumentType::create($data);

            $this->logActivity('CREATE', 'Document Type', "Created document type: {$documentType->document_name}", $data);

            return response()->json([
                'status' => 'success',
                'message' => 'Document type created successfully',
                'data' => $documentType,
            ], 201);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create document type',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Display the specified document type.
     */
    public function show(string $id)
    {
        try {
            $documentType = DocumentType::find($id);

            if (! $documentType) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Document type not found',
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Document type retrieved successfully',
                'data' => $documentType,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve document type',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Update the specified document type in storage.
     */
    public function update(UpdateDocumentTypeRequest $request, string $id)
    {
        try {
            $documentType = DocumentType::find($id);

            if (! $documentType) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Document type not found',
                ], 404);
            }

            $data = $request->validated();
            $documentType->update($data);

            $this->logActivity('UPDATE', 'Document Type', "Updated document type: {$documentType->document_name}", $data);

            return response()->json([
                'status' => 'success',
                'message' => 'Document type updated successfully',
                'data' => $documentType,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update document type',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Remove the specified document type from storage.
     */
    public function destroy(string $id)
    {
        try {
            $documentType = DocumentType::find($id);

            if (! $documentType) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Document type not found',
                ], 404);
            }

            if (! auth('api')->user()->hasRole('Super Admin')) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Only Super Admin can delete document types',
                ], 403);
            }

            $documentName = $documentType->document_name;
            $documentType->delete();

            $this->logActivity('DELETE', 'Document Type', "Deleted document type: {$documentName}");

            return response()->json([
                'status' => 'success',
                'message' => 'Document type deleted successfully',
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete document type',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Get a list of document types (lightweight list).
     */
    public function getDocumentTypeList()
    {
        try {
            $documentTypes = DocumentType::active()->orderBy('document_name', 'asc')
                ->get(['id', 'document_name', 'slug', 'is_required']);

            return response()->json([
                'status' => 'success',
                'message' => 'Document types retrieved successfully',
                'data' => $documentTypes,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve document types',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Toggle the active status of the specified document type.
     */
    public function toggleStatus(string $id)
    {
        try {
            $documentType = DocumentType::find($id);

            if (! $documentType) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Document type not found',
                ], 404);
            }

            $documentType->is_active = ! $documentType->is_active;
            $documentType->save();

            $this->logActivity('TOGGLE_STATUS', 'Document Type', "Toggled document type status: {$documentType->document_name} (".($documentType->is_active ? 'Active' : 'Inactive').')');

            return response()->json([
                'status' => 'success',
                'message' => 'Document type status updated successfully',
                'data' => [
                    'id' => $documentType->id,
                    'is_active' => $documentType->is_active,
                ],
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to toggle document type status',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }
}
