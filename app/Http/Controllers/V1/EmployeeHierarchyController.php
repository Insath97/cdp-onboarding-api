<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReassignReportingManagerRequest;
use App\Models\Employee;
use App\Models\EmployeeHierarchyHistory;
use App\Models\User;
use App\Notifications\ManagerReassignedNotification;
use App\Traits\ActivityLogTrait;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;

class EmployeeHierarchyController extends Controller implements HasMiddleware
{
    use ActivityLogTrait;

    /**
     * Define the middleware for this controller.
     */
    public static function middleware(): array
    {
        return [
            new Middleware('permission:Employee Hierarchy View', ['getHistory', 'getHierarchyAsOf']),
            new Middleware('permission:Employee Hierarchy Reassign', ['reassignManager']),
        ];
    }

    /**
     * Reassign an employee's reporting manager and track historical date ranges.
     */
    public function reassignManager(ReassignReportingManagerRequest $request, string $id)
    {
        try {
            $employee = Employee::find($id);

            if (!$employee) {
                $this->logActivity('REASSIGN_MANAGER_FAILED', 'EmployeeHierarchy', "Employee not found with ID: {$id}", ['id' => $id], 'warning');
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Employee not found',
                ], 404);
            }

            $effectiveFrom = $request->effective_from;
            $newManagerId  = $request->reporting_manager_id;
            $reason        = $request->reason;
            $changedBy     = auth()->id();

            DB::beginTransaction();

            // Find current active history record
            $activeHistory = EmployeeHierarchyHistory::where('employee_id', $employee->id)
                ->current()
                ->first();

            if ($activeHistory) {
                $newEffectiveFrom = Carbon::parse($effectiveFrom);
                $prevEffectiveFrom = Carbon::parse($activeHistory->effective_from);

                // Close active history record (effective_to = new effective_from - 1 day)
                $closeDate = $newEffectiveFrom->copy()->subDay()->format('Y-m-d');
                if ($closeDate < $prevEffectiveFrom->format('Y-m-d')) {
                    $closeDate = $prevEffectiveFrom->format('Y-m-d');
                }

                $activeHistory->effective_to = $closeDate;
                $activeHistory->save();
            }

            // Create new active history record
            $newHistory = EmployeeHierarchyHistory::create([
                'employee_id'          => $employee->id,
                'reporting_manager_id' => $newManagerId,
                'effective_from'       => $effectiveFrom,
                'effective_to'         => null,
                'reason'               => $reason ?? 'Manager Reassignment',
                'changed_by'           => $changedBy,
            ]);

            // Update live employee record
            $employee->reporting_manager_id = $newManagerId;
            $employee->save();

            // Dispatch notification to employee and new manager
            $newManager = $newManagerId ? Employee::find($newManagerId) : null;
            $actorUser = auth()->user();

            $employeeUser = User::where('employee_id', $employee->id)->first();
            if ($employeeUser) {
                $employeeUser->notify(new ManagerReassignedNotification($employee, $newManager, $effectiveFrom, $actorUser));
            }

            if ($newManager) {
                $newManagerUser = User::where('employee_id', $newManager->id)->first();
                if ($newManagerUser && $newManagerUser->id !== ($employeeUser?->id)) {
                    $newManagerUser->notify(new ManagerReassignedNotification($employee, $newManager, $effectiveFrom, $actorUser));
                }
            }

            DB::commit();

            $employee->load([
                'reportingManager:id,f_name,l_name,full_name,employee_code',
                'department:id,name',
                'designation:id,name',
            ]);

            $this->logActivity(
                'REASSIGN_MANAGER',
                'EmployeeHierarchy',
                "Reassigned reporting manager for employee ID: {$employee->id}",
                [
                    'employee_id'          => $employee->id,
                    'new_manager_id'       => $newManagerId,
                    'effective_from'       => $effectiveFrom,
                    'history_record_id'    => $newHistory->id,
                ]
            );

            return response()->json([
                'status'  => 'success',
                'message' => 'Employee reporting manager reassigned successfully',
                'data'    => [
                    'employee'       => $employee,
                    'new_history'    => $newHistory->load('reportingManager:id,f_name,l_name,full_name,employee_code', 'changedBy:id,name,email'),
                    'closed_history' => $activeHistory ? $activeHistory->fresh() : null,
                ],
            ], 200);

        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'status'  => 'error',
                'message' => 'Failed to reassign reporting manager',
                'error'   => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Get complete reporting manager history timeline for an employee.
     */
    public function getHistory(Request $request, string $id)
    {
        try {
            $employee = Employee::find($id);

            if (!$employee) {
                $this->logActivity('GET_HISTORY_FAILED', 'EmployeeHierarchy', "Employee not found with ID: {$id}", ['id' => $id], 'warning');
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Employee not found',
                ], 404);
            }

            $histories = EmployeeHierarchyHistory::where('employee_id', $employee->id)
                ->with([
                    'reportingManager:id,f_name,l_name,full_name,employee_code,designation_id',
                    'reportingManager.designation:id,name',
                    'changedBy:id,name,email',
                ])
                ->orderBy('effective_from', 'desc')
                ->orderBy('id', 'desc')
                ->get();

            $this->logActivity(
                'GET_HISTORY',
                'EmployeeHierarchy',
                "Retrieved reporting manager history for employee ID: {$id}",
                ['employee_id' => $id]
            );

            return response()->json([
                'status'  => 'success',
                'message' => 'Employee reporting manager history retrieved successfully',
                'data'    => [
                    'employee'  => $employee->only(['id', 'employee_code', 'full_name', 'email', 'reporting_manager_id']),
                    'histories' => $histories,
                ],
            ], 200);

        } catch (\Throwable $th) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Failed to retrieve manager history',
                'error'   => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Resolve and return point-in-time hierarchy for an employee as of a specific date.
     */
    public function getHierarchyAsOf(Request $request, string $id)
    {
        try {
            $employee = Employee::with(['designation:id,name', 'department:id,name'])->find($id);

            if (!$employee) {
                $this->logActivity('GET_HIERARCHY_AS_OF_FAILED', 'EmployeeHierarchy', "Employee not found with ID: {$id}", ['id' => $id], 'warning');
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Employee not found',
                ], 404);
            }

            $asOfDate = $request->query('as_of_date', now()->format('Y-m-d'));

            $manager = $employee->resolveReportingManagerAsOf($asOfDate);
            if ($manager) {
                $manager->load(['designation:id,name', 'department:id,name']);
            }

            $subordinates = $employee->resolveSubordinatesAsOf($asOfDate);
            $channelHeadRecord = $employee->resolveChannelAsOf($asOfDate);

            $this->logActivity(
                'GET_HIERARCHY_AS_OF',
                'EmployeeHierarchy',
                "Retrieved hierarchy for employee ID: {$id} as of {$asOfDate}",
                ['employee_id' => $id, 'as_of_date' => $asOfDate]
            );

            return response()->json([
                'status'  => 'success',
                'message' => "Employee hierarchy as of {$asOfDate} retrieved successfully",
                'data'    => [
                    'as_of_date'         => $asOfDate,
                    'employee'           => $employee,
                    'reporting_manager'  => $manager,
                    'direct_subordinates'=> $subordinates,
                    'channel'            => $channelHeadRecord ? [
                        'channel_id'   => $channelHeadRecord->channel_wise_employment_id,
                        'channel_name' => $channelHeadRecord->channel?->name,
                        'channel_head' => $channelHeadRecord->employee?->full_name,
                    ] : null,
                ],
            ], 200);

        } catch (\Throwable $th) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Failed to retrieve point-in-time hierarchy',
                'error'   => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }
}
