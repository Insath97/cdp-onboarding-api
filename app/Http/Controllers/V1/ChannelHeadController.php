<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\AssignChannelHeadRequest;
use App\Models\ChannelHeadHistory;
use App\Models\ChannelWiseEmployment;
use App\Models\Employee;
use App\Traits\ActivityLogTrait;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;

class ChannelHeadController extends Controller implements HasMiddleware
{
    use ActivityLogTrait;

    public static function middleware(): array
    {
        return [
            new Middleware('permission:Channel Head Index',  ['getCurrentHead', 'getHistory']),
            new Middleware('permission:Channel Head Assign', ['assignHead']),
        ];
    }

    /**
     * GET /channel-wise-employments/{id}/head
     * Returns the current active Channel Head for the given channel.
     */
    public function getCurrentHead(string $id)
    {
        try {
            $channel = ChannelWiseEmployment::find($id);

            if (! $channel) {
                return response()->json(['status' => 'error', 'message' => 'Channel not found.'], 404);
            }

            $head = ChannelHeadHistory::with(['employee.user', 'changedBy'])
                ->where('channel_wise_employment_id', $id)
                ->current()
                ->first();

            return response()->json([
                'status'  => 'success',
                'message' => 'Current channel head retrieved successfully.',
                'data'    => [
                    'channel' => [
                        'id'   => $channel->id,
                        'name' => $channel->name,
                    ],
                    'current_head' => $head,
                ],
            ], 200);

        } catch (\Throwable $th) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Failed to retrieve channel head.',
                'error'   => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * POST /channel-wise-employments/{id}/head
     * Assign a new Channel Head. Automatically closes the previous active record.
     */
    public function assignHead(AssignChannelHeadRequest $request, string $id)
    {
        try {
            $channel = ChannelWiseEmployment::find($id);

            if (! $channel) {
                return response()->json(['status' => 'error', 'message' => 'Channel not found.'], 404);
            }

            $employee = Employee::find($request->employee_id);

            if (! $employee || ! $employee->is_active) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'The selected employee does not exist or is not active.',
                ], 422);
            }

            $effectiveFrom = $request->effective_from;

            // Validate: new effective_from must not be before current head's effective_from
            $currentHead = ChannelHeadHistory::where('channel_wise_employment_id', $id)
                ->current()
                ->first();

            if ($currentHead && $effectiveFrom <= $currentHead->effective_from->format('Y-m-d')) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'The effective date must be after the current head\'s start date (' . $currentHead->effective_from->format('Y-m-d') . ').',
                ], 422);
            }

            $newRecord = DB::transaction(function () use ($id, $employee, $effectiveFrom, $request, $currentHead) {

                // Close the previous active head record
                if ($currentHead) {
                    $previousEffectiveTo = date('Y-m-d', strtotime($effectiveFrom . ' -1 day'));
                    $currentHead->update(['effective_to' => $previousEffectiveTo]);
                }

                // Insert the new channel head record
                return ChannelHeadHistory::create([
                    'channel_wise_employment_id' => $id,
                    'employee_id'                => $employee->id,
                    'effective_from'             => $effectiveFrom,
                    'effective_to'               => null,
                    'remarks'                    => $request->remarks,
                    'changed_by'                 => auth()->id(),
                ]);
            });

            $newRecord->load(['employee.user', 'channel', 'changedBy']);

            $this->logActivity(
                'ASSIGN',
                'ChannelHeadHistory',
                "Assigned {$employee->full_name} as Channel Head for {$channel->name} from {$effectiveFrom}",
                $request->validated()
            );

            return response()->json([
                'status'  => 'success',
                'message' => "{$employee->full_name} has been assigned as Channel Head for {$channel->name}.",
                'data'    => $newRecord,
            ], 201);

        } catch (\Throwable $th) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Failed to assign channel head.',
                'error'   => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * GET /channel-wise-employments/{id}/head/history
     * Returns the full paginated history of all Channel Head assignments for a channel.
     */
    public function getHistory(Request $request, string $id)
    {
        try {
            $channel = ChannelWiseEmployment::find($id);

            if (! $channel) {
                return response()->json(['status' => 'error', 'message' => 'Channel not found.'], 404);
            }

            $perPage = $request->get('per_page', 15);

            $history = ChannelHeadHistory::with(['employee.user', 'changedBy'])
                ->where('channel_wise_employment_id', $id)
                ->orderBy('effective_from', 'desc')
                ->paginate($perPage);

            return response()->json([
                'status'  => 'success',
                'message' => 'Channel head history retrieved successfully.',
                'data'    => [
                    'channel' => [
                        'id'   => $channel->id,
                        'name' => $channel->name,
                    ],
                    'history' => $history,
                ],
            ], 200);

        } catch (\Throwable $th) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Failed to retrieve channel head history.',
                'error'   => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }
}
