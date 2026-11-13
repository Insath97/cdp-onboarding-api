<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Models\ChannelHeadHistory;
use App\Models\ChannelWiseEmployment;
use App\Models\Employee;
use App\Traits\ActivityLogTrait;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class ReportController extends Controller implements HasMiddleware
{
    use ActivityLogTrait;

    /**
     * Define the middleware for this controller.
     */
    public static function middleware(): array
    {
        return [
            new Middleware('permission:Report Channel Wise View', ['channelWiseHierarchyReport']),
            new Middleware('permission:Report View', ['employeeHierarchyReport']),
        ];
    }

    /**
     * GET /api/v1/reports/channel-wise-hierarchy
     *
     * Channel-wise report:
     * - Channel details
     * - Active Channel Head
     * - Under that Head: designation-wise hierarchy with user & employee counts
     */
    public function channelWiseHierarchyReport(Request $request): JsonResponse
    {
        try {
            $channelId = $request->query('channel_id');

            // 1. Retrieve channels
            $channelQuery = ChannelWiseEmployment::query()->where('is_active', true);
            if ($channelId) {
                $channelQuery->where('id', $channelId);
            }
            $channels = $channelQuery->orderBy('name')->get();

            if ($channels->isEmpty()) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'No active channels found.',
                    'data' => [],
                ], 200);
            }

            // 2. Retrieve active Channel Heads for these channels
            $currentHeads = ChannelHeadHistory::with([
                'employee.user',
                'employee.designation',
            ])
                ->whereIn('channel_wise_employment_id', $channels->pluck('id'))
                ->current()
                ->get()
                ->keyBy('channel_wise_employment_id');

            // 3. Retrieve all active employees with designations and user accounts for hierarchy tree building
            $allEmployees = Employee::with([
                'user:id,employee_id,username,email,is_active,can_login',
                'designation:id,name,code,level,order_weight',
            ])
                ->where('is_active', true)
                ->select([
                    'id',
                    'full_name',
                    'employee_code',
                    'email',
                    'reporting_manager_id',
                    'designation_id',
                    'is_active',
                ])
                ->get();

            // 4. Build adjacency map (manager_id => subordinates)
            $subordinatesByManager = [];
            foreach ($allEmployees as $emp) {
                if ($emp->reporting_manager_id) {
                    $subordinatesByManager[$emp->reporting_manager_id][] = $emp;
                }
            }

            // 5. Build report per channel
            $report = [];

            foreach ($channels as $channel) {
                /** @var ChannelHeadHistory|null $headRecord */
                $headRecord = $currentHeads->get($channel->id);
                $headEmp = $headRecord?->employee;

                if (! $headEmp) {
                    $report[] = [
                        'channel' => [
                            'id' => $channel->id,
                            'name' => $channel->name,
                            'description' => $channel->description,
                        ],
                        'channel_head' => null,
                        'total_users_count' => 0,
                        'total_employees_count' => 0,
                        'designations_hierarchy' => [],
                    ];

                    continue;
                }

                // Traverse the reporting hierarchy downwards starting from this Channel Head
                $descendants = [];
                $queue = [$headEmp->id];
                $visited = [$headEmp->id => true];

                while (! empty($queue)) {
                    $currentId = array_shift($queue);
                    if (isset($subordinatesByManager[$currentId])) {
                        foreach ($subordinatesByManager[$currentId] as $subordinate) {
                            if (! isset($visited[$subordinate->id])) {
                                $visited[$subordinate->id] = true;
                                $descendants[] = $subordinate;
                                $queue[] = $subordinate->id;
                            }
                        }
                    }
                }

                // Group descendants under this Head by Designation
                $designationGroups = [];

                foreach ($descendants as $descendant) {
                    $desigId = $descendant->designation_id ?? 0;
                    $desig = $descendant->designation;

                    if (! isset($designationGroups[$desigId])) {
                        $designationGroups[$desigId] = [
                            'designation_id' => $desigId ?: null,
                            'designation_name' => $desig?->name ?? 'Unassigned Designation',
                            'designation_code' => $desig?->code ?? 'N/A',
                            'level' => $desig?->level ?? 'entry',
                            'order_weight' => (int) ($desig?->order_weight ?? 0),
                            'users_count' => 0,
                            'employees_count' => 0,
                        ];
                    }

                    $designationGroups[$desigId]['employees_count']++;

                    // Count users who have an active login user account
                    if ($descendant->user && $descendant->user->is_active && $descendant->user->can_login) {
                        $designationGroups[$desigId]['users_count']++;
                    }
                }

                // Sort designations by hierarchy order_weight descending (Director -> Manager -> Executive -> etc.)
                usort($designationGroups, function ($a, $b) {
                    if ($a['order_weight'] !== $b['order_weight']) {
                        return $b['order_weight'] <=> $a['order_weight'];
                    }

                    return strcmp($a['designation_name'], $b['designation_name']);
                });

                $totalEmployees = array_sum(array_column($designationGroups, 'employees_count'));
                $totalUsers = array_sum(array_column($designationGroups, 'users_count'));

                $report[] = [
                    'channel' => [
                        'id' => $channel->id,
                        'name' => $channel->name,
                        'description' => $channel->description,
                    ],
                    'channel_head' => [
                        'employee_id' => $headEmp->id,
                        'employee_code' => $headEmp->employee_code,
                        'full_name' => $headEmp->full_name,
                        'email' => $headEmp->email,
                        'effective_from' => $headRecord->effective_from ? Carbon::parse($headRecord->effective_from)->format('Y-m-d') : null,
                        'designation' => $headEmp->designation ? [
                            'id' => $headEmp->designation->id,
                            'name' => $headEmp->designation->name,
                            'code' => $headEmp->designation->code,
                            'level' => $headEmp->designation->level,
                            'order_weight' => (int) $headEmp->designation->order_weight,
                        ] : null,
                    ],
                    'total_users_count' => $totalUsers,
                    'total_employees_count' => $totalEmployees,
                    'designations_hierarchy' => array_values($designationGroups),
                ];
            }

            $this->logActivity('VIEW_REPORT', 'Report', 'Viewed channel-wise designation hierarchy report');

            return response()->json([
                'status' => 'success',
                'message' => 'Channel-wise designation hierarchy report retrieved successfully.',
                'data' => $report,
            ], 200);

        } catch (\Throwable $th) {
            $this->logActivity('ERROR', 'Report', 'Failed to generate channel-wise hierarchy report: '.$th->getMessage(), null, 'error');

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to generate channel-wise hierarchy report.',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * GET /api/v1/reports/employee-hierarchy
     *
     * Nested employee hierarchy tree with combined filters:
     * channel_id, branch_id, designation_id, department_id, search, is_active
     */
    public function employeeHierarchyReport(Request $request): JsonResponse
    {
        try {
            $isActive = $request->filled('is_active') ? $request->boolean('is_active') : true;

            $filtersApplied = [
                'channel_id' => $request->filled('channel_id') ? $request->integer('channel_id') : null,
                'branch_id' => $request->filled('branch_id') ? $request->integer('branch_id') : null,
                'designation_id' => $request->filled('designation_id') ? $request->integer('designation_id') : null,
                'department_id' => $request->filled('department_id') ? $request->integer('department_id') : null,
                'search' => $request->filled('search') ? (string) $request->query('search') : null,
                'is_active' => $isActive,
            ];

            // 1. Base hierarchy map (id => manager, manager => children) for the active set
            $baseRows = Employee::query()
                ->where('is_active', $isActive)
                ->select(['id', 'reporting_manager_id'])
                ->get();

            $managerOf = [];
            $childrenOf = [];
            foreach ($baseRows as $row) {
                $managerOf[$row->id] = $row->reporting_manager_id;
                if ($row->reporting_manager_id) {
                    $childrenOf[$row->reporting_manager_id][] = $row->id;
                }
            }

            // 2. Channel scope: employees under the current channel head (BFS down)
            $channelScopeIds = null;
            if ($filtersApplied['channel_id'] !== null) {
                $headRecord = ChannelHeadHistory::query()
                    ->where('channel_wise_employment_id', $filtersApplied['channel_id'])
                    ->current()
                    ->first();

                $headId = $headRecord?->employee_id;

                if (! $headId || ! array_key_exists($headId, $managerOf)) {
                    $this->logActivity('VIEW_REPORT', 'Report', 'Viewed employee hierarchy report');

                    return response()->json([
                        'status' => 'success',
                        'message' => 'No active channel head found for the selected channel.',
                        'data' => [],
                        'meta' => [
                            'total_employees_count' => 0,
                            'total_users_count' => 0,
                            'matched_count' => 0,
                            'filters_applied' => $filtersApplied,
                        ],
                    ], 200);
                }

                $channelScopeIds = [$headId => true];
                $queue = [$headId];
                while (! empty($queue)) {
                    $currentId = array_shift($queue);
                    foreach ($childrenOf[$currentId] ?? [] as $childId) {
                        if (! isset($channelScopeIds[$childId])) {
                            $channelScopeIds[$childId] = true;
                            $queue[] = $childId;
                        }
                    }
                }
            }

            // 3. Matched employees (SQL filters, AND-combined)
            $matchedQuery = Employee::query()->where('is_active', $isActive);

            if ($filtersApplied['branch_id'] !== null) {
                $matchedQuery->where('branch_id', $filtersApplied['branch_id']);
            }
            if ($filtersApplied['designation_id'] !== null) {
                $matchedQuery->where('designation_id', $filtersApplied['designation_id']);
            }
            if ($filtersApplied['department_id'] !== null) {
                $matchedQuery->where('department_id', $filtersApplied['department_id']);
            }
            if ($filtersApplied['search'] !== null) {
                $matchedQuery->search($filtersApplied['search']);
            }
            if ($channelScopeIds !== null) {
                $matchedQuery->whereIn('id', array_keys($channelScopeIds));
            }

            $matchedIds = $matchedQuery->pluck('id')->flip()->all();

            // 4. Keep matched employees plus their ancestors so the tree has no orphans
            $keptIds = [];
            foreach (array_keys($matchedIds) as $employeeId) {
                $keptIds[$employeeId] = true;

                $currentId = $employeeId;
                while (true) {
                    $managerId = $managerOf[$currentId] ?? null;

                    if (! $managerId || ! array_key_exists($managerId, $managerOf) || isset($keptIds[$managerId])) {
                        break;
                    }
                    if ($channelScopeIds !== null && ! isset($channelScopeIds[$managerId])) {
                        break;
                    }

                    $keptIds[$managerId] = true;
                    $currentId = $managerId;
                }
            }

            // 5. Load kept employees with relations and build the tree
            $keptEmployees = Employee::with([
                'user:id,employee_id,username,email,is_active,can_login',
                'designation:id,name,code,level,order_weight',
                'branch:id,name,code',
            ])
                ->whereIn('id', array_keys($keptIds))
                ->get()
                ->keyBy('id');

            $childrenByManager = [];
            foreach ($keptEmployees as $employee) {
                if ($employee->reporting_manager_id && $keptEmployees->has($employee->reporting_manager_id)) {
                    $childrenByManager[$employee->reporting_manager_id][] = $employee;
                }
            }

            $roots = $keptEmployees->filter(
                fn (Employee $employee) => ! $employee->reporting_manager_id
                    || ! $keptEmployees->has($employee->reporting_manager_id)
            )->values()->all();

            $visited = [];
            $tree = $this->buildHierarchyModelTree($roots, $childrenByManager, $visited);
            $report = $this->sortHierarchyNodes($this->formatHierarchyNodes($tree, $matchedIds));
            $summary = $this->summarizeHierarchyNodes($report);

            $this->logActivity('VIEW_REPORT', 'Report', 'Viewed employee hierarchy report');

            return response()->json([
                'status' => 'success',
                'message' => 'Employee hierarchy report retrieved successfully.',
                'data' => $report,
                'meta' => [
                    'total_employees_count' => $summary['employees'],
                    'total_users_count' => $summary['users'],
                    'matched_count' => count($matchedIds),
                    'filters_applied' => $filtersApplied,
                ],
            ], 200);

        } catch (\Throwable $th) {
            $this->logActivity('ERROR', 'Report', 'Failed to generate employee hierarchy report: '.$th->getMessage(), null, 'error');

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to generate employee hierarchy report.',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    private function buildHierarchyModelTree(array $employees, array $childrenByManager, array &$visited): array
    {
        $tree = [];

        foreach ($employees as $employee) {
            if (isset($visited[$employee->id])) {
                continue;
            }

            $visited[$employee->id] = true;

            $tree[] = [
                'employee' => $employee,
                'children' => $this->buildHierarchyModelTree(
                    $childrenByManager[$employee->id] ?? [],
                    $childrenByManager,
                    $visited
                ),
            ];
        }

        return $tree;
    }

    private function formatHierarchyNodes(array $tree, array $matchedIds): array
    {
        $nodes = [];

        foreach ($tree as $item) {
            $children = $this->sortHierarchyNodes($this->formatHierarchyNodes($item['children'], $matchedIds));

            $nodes[] = $this->formatHierarchyEmployeeNode(
                $item['employee'],
                $children,
                isset($matchedIds[$item['employee']->id])
            );
        }

        return $nodes;
    }

    private function formatHierarchyEmployeeNode(Employee $employee, array $children, bool $matchesFilter): array
    {
        $subordinatesCount = 0;
        foreach ($children as $child) {
            $subordinatesCount += 1 + $child['total_subordinates_count'];
        }

        return [
            'id' => $employee->id,
            'employee_code' => $employee->employee_code,
            'full_name' => $employee->full_name,
            'email' => $employee->email,
            'reporting_manager_id' => $employee->reporting_manager_id,
            'is_active' => (bool) $employee->is_active,
            'matches_filter' => $matchesFilter,
            'designation' => $employee->designation ? [
                'id' => $employee->designation->id,
                'name' => $employee->designation->name,
                'code' => $employee->designation->code,
                'level' => $employee->designation->level,
                'order_weight' => (int) $employee->designation->order_weight,
            ] : null,
            'branch' => $employee->branch ? [
                'id' => $employee->branch->id,
                'name' => $employee->branch->name,
                'code' => $employee->branch->code,
            ] : null,
            'user' => $employee->user ? [
                'id' => $employee->user->id,
                'username' => $employee->user->username,
                'email' => $employee->user->email,
                'is_active' => (bool) $employee->user->is_active,
                'can_login' => (bool) $employee->user->can_login,
            ] : null,
            'total_subordinates_count' => $subordinatesCount,
            'children' => $children,
        ];
    }

    private function sortHierarchyNodes(array $nodes): array
    {
        usort($nodes, function (array $a, array $b) {
            $aWeight = $a['designation']['order_weight'] ?? -1;
            $bWeight = $b['designation']['order_weight'] ?? -1;

            if ($aWeight !== $bWeight) {
                return $bWeight <=> $aWeight;
            }

            return strcmp($a['full_name'], $b['full_name']);
        });

        return $nodes;
    }

    private function summarizeHierarchyNodes(array $nodes): array
    {
        $summary = ['employees' => 0, 'users' => 0];

        foreach ($nodes as $node) {
            $summary['employees']++;
            if ($node['user'] !== null) {
                $summary['users']++;
            }

            $children = $this->summarizeHierarchyNodes($node['children']);
            $summary['employees'] += $children['employees'];
            $summary['users'] += $children['users'];
        }

        return $summary;
    }
}
