<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\SSOIntrospectRequest;
use App\Models\User;
use App\Traits\ActivityLogTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use PHPOpenSourceSaver\JWTAuth\Exceptions\TokenExpiredException;
use PHPOpenSourceSaver\JWTAuth\Exceptions\TokenInvalidException;
use PHPOpenSourceSaver\JWTAuth\Exceptions\JWTException;

class SSOController extends Controller
{
    use ActivityLogTrait;

    /**
     * Introspect a JWT token and retrieve user details with roles & permissions.
     */
    public function introspect(SSOIntrospectRequest $request): JsonResponse
    {
        $token = $request->input('token');

        try {
            /** @var \PHPOpenSourceSaver\JWTAuth\JWTGuard $guard */
            $guard = Auth::guard('api');
            $user = $guard->setToken($token)->user();

            if (!$user) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'User not found or token has expired',
                    'active' => false
                ], 401);
            }

            if (!$user->canLogin()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'User account is deactivated',
                    'active' => false
                ], 403);
            }

            $user->load([
                'roles.permissions',
                'permissions',
                'employee.branch',
                'employee.zonal',
                'employee.region',
                'employee.province',
                'employee.designation',
                'employee.department'
            ]);

            $directPermissions = $user->permissions->pluck('name');
            $rolePermissions = $user->roles->flatMap(function ($role) {
                return $role->permissions->pluck('name');
            });
            $allPermissions = $directPermissions->concat($rolePermissions)->unique()->values()->all();

            $rolesList = $user->roles->pluck('name')->all();

            $employeeData = null;
            if ($user->user_type === 'staff' && $user->employee) {
                $emp = $user->employee;
                $employeeData = [
                    'employee_code' => $emp->employee_code,
                    'full_name' => $emp->full_name,
                    'email' => $emp->email,
                    'phone' => $emp->phone_primary,
                    'branch' => $emp->branch ? [
                        'id' => $emp->branch->id,
                        'name' => $emp->branch->name,
                        'code' => $emp->branch->code,
                    ] : null,
                    'department' => $emp->department ? [
                        'id' => $emp->department->id,
                        'name' => $emp->department->name,
                    ] : null,
                    'designation' => $emp->designation ? [
                        'id' => $emp->designation->id,
                        'name' => $emp->designation->name,
                    ] : null,
                    'province' => $emp->province ? ['id' => $emp->province->id, 'name' => $emp->province->name] : null,
                    'region' => $emp->region ? ['id' => $emp->region->id, 'name' => $emp->region->name] : null,
                    'zonal' => $emp->zonal ? ['id' => $emp->zonal->id, 'name' => $emp->zonal->name] : null,
                ];
            }

            $this->logActivity(
                action: 'SSO_INTROSPECT_SUCCESS',
                module: 'SSO',
                description: "SSO token introspected successfully for user: {$user->username}",
                payload: [
                    'user_id' => $user->id
                ]
            );

            return response()->json([
                'status' => 'success',
                'active' => true,
                'data' => [
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'username' => $user->username,
                        'email' => $user->email,
                        'user_type' => $user->user_type,
                    ],
                    'employee' => $employeeData,
                    'roles' => $rolesList,
                    'permissions' => $allPermissions
                ]
            ], 200);

        } catch (TokenExpiredException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Token has expired',
                'active' => false
            ], 401);
        } catch (TokenInvalidException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Token is invalid',
                'active' => false
            ], 401);
        } catch (JWTException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Token error: ' . $e->getMessage(),
                'active' => false
            ], 401);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Server error during introspection',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
                'active' => false
            ], 500);
        }
    }
}
