<?php

use App\Models\Candidate;
use App\Models\Department;
use App\Models\Designation;
use App\Models\InterviewApproval;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

uses(RefreshDatabase::class);

function clpMakeUser(string $email, string $name): User
{
    return User::firstOrCreate(
        ['email' => $email],
        [
            'name' => $name,
            'username' => str_replace(['@', '.'], ['_', '_'], $email),
            'password' => bcrypt('password'),
            'user_type' => 'staff',
            'is_active' => true,
            'can_login' => true,
        ]
    );
}

function clpMakeRole(string $roleName, array $permissionNames): Role
{
    $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'api']);

    $permissions = Permission::where('guard_name', 'api')
        ->whereIn('name', $permissionNames)
        ->get();

    $role->syncPermissions($permissions);

    return $role;
}

function clpMakeCandidate(User $uploader, Designation $designation, string $status, string $suffix): Candidate
{
    return Candidate::create([
        'first_name'       => 'Test',
        'last_name'        => "Candidate {$suffix}",
        'nic_number'       => "NIC{$suffix}",
        'phone_number'     => "077{$suffix}",
        'designation_id'   => $designation->id,
        'expected_salary'  => 50000,
        'cv_path'          => '',
        'status'           => $status,
        'uploaded_by'      => $uploader->id,
        'submitted_at'     => now(),
    ]);
}

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'PermissionsSeeder']);

    $department = Department::create(['name' => 'CLP Department', 'code' => 'CLP-DEPT']);
    $this->designation = Designation::create([
        'name'          => 'Test Designation',
        'code'          => 'CLP-DESIG',
        'department_id' => $department->id,
        'level'         => 'entry',
        'is_active'     => true,
    ]);
    $this->uploader = clpMakeUser('clp_uploader@test.com', 'CLP Uploader');
});

it('shows only 2nd interview stage statuses to Candidate 2nd Interview List holders', function () {
    $viewer = clpMakeUser('clp_viewer@test.com', 'CLP Viewer');
    $viewer->assignRole(clpMakeRole('CLP 2nd List Viewer', ['Candidate 2nd Interview List']));

    clpMakeCandidate($this->uploader, $this->designation, 'pending', '001');
    clpMakeCandidate($this->uploader, $this->designation, '1st_interview_passed', '002');
    clpMakeCandidate($this->uploader, $this->designation, '2nd_interview', '003');
    clpMakeCandidate($this->uploader, $this->designation, '2nd_interview_rejected', '004');
    clpMakeCandidate($this->uploader, $this->designation, '2nd_interview_passed', '005');

    $token = JWTAuth::fromUser($viewer);

    $response = $this->withHeader('Authorization', "Bearer {$token}")
                     ->getJson('/api/v1/candidates');

    $response->assertStatus(200);

    $statuses = collect($response->json('data.data'))->pluck('status')->unique()->sort()->values()->all();

    expect($statuses)->toBe(['1st_interview_passed', '2nd_interview', '2nd_interview_rejected']);
});

it('shows only assigned candidates to Candidate Assigned List holders', function () {
    $assignee = clpMakeUser('clp_assignee@test.com', 'CLP Assignee');
    $assignee->assignRole(clpMakeRole('CLP Assigned Viewer', ['Candidate Assigned List']));

    $uploader2 = clpMakeUser('clp_uploader2@test.com', 'CLP Uploader 2');

    $assigned = clpMakeCandidate($this->uploader, $this->designation, '2nd_interview', '011');
    $notAssigned = clpMakeCandidate($uploader2, $this->designation, '1st_interview', '012');

    InterviewApproval::create([
        'candidate_id'        => $assigned->id,
        'actor_id'            => $this->uploader->id,
        'assigned_to_user_id' => $assignee->id,
        'stage'               => '2nd_interview',
        'action'              => 'assigned',
        'assigned_date'       => now()->toDateString(),
        'comments'            => 'Assigned for testing',
        'status'              => 'pending',
    ]);

    $token = JWTAuth::fromUser($assignee);

    $response = $this->withHeader('Authorization', "Bearer {$token}")
                     ->getJson('/api/v1/candidates');

    $response->assertStatus(200);

    $ids = collect($response->json('data.data'))->pluck('id')->all();

    expect($ids)->toBe([$assigned->id]);
});

it('returns 403 for users without any candidate list permission', function () {
    $plain = clpMakeUser('clp_plain@test.com', 'CLP Plain User');

    $token = JWTAuth::fromUser($plain);

    $this->withHeader('Authorization', "Bearer {$token}")
         ->getJson('/api/v1/candidates')
         ->assertStatus(403);
});

it('prevents unassigned users from approving a 2nd interview', function () {
    $interviewer = clpMakeUser('clp_interviewer@test.com', 'CLP Interviewer');
    $interviewer->assignRole(clpMakeRole('CLP Approve 2nd', ['Candidate Approve 2nd Interview']));

    $otherAssignee = clpMakeUser('clp_other_assignee@test.com', 'CLP Other Assignee');

    $candidate = clpMakeCandidate($this->uploader, $this->designation, '2nd_interview', '021');

    InterviewApproval::create([
        'candidate_id'        => $candidate->id,
        'actor_id'            => $this->uploader->id,
        'assigned_to_user_id' => $otherAssignee->id,
        'stage'               => '2nd_interview',
        'action'              => 'assigned',
        'assigned_date'       => now()->toDateString(),
        'comments'            => 'Assigned to someone else',
        'status'              => 'pending',
    ]);

    $token = JWTAuth::fromUser($interviewer);

    $this->withHeader('Authorization', "Bearer {$token}")
         ->postJson("/api/v1/candidates/{$candidate->id}/approve-2nd-interview", [
             'interview_date' => now()->toDateString(),
             'comments'       => 'Attempting unauthorized approval',
         ])
         ->assertStatus(403);

    expect($candidate->fresh()->status)->toBe('2nd_interview');
});

it('allows the assigned interviewer to approve the 2nd interview', function () {
    $interviewer = clpMakeUser('clp_assigned_approver@test.com', 'CLP Assigned Approver');
    $interviewer->assignRole(clpMakeRole('CLP Assigned Approver Role', ['Candidate Approve 2nd Interview']));

    $candidate = clpMakeCandidate($this->uploader, $this->designation, '2nd_interview', '031');

    InterviewApproval::create([
        'candidate_id'        => $candidate->id,
        'actor_id'            => $this->uploader->id,
        'assigned_to_user_id' => $interviewer->id,
        'stage'               => '2nd_interview',
        'action'              => 'assigned',
        'assigned_date'       => now()->toDateString(),
        'comments'            => 'Assigned for approval test',
        'status'              => 'pending',
    ]);

    $token = JWTAuth::fromUser($interviewer);

    $this->withHeader('Authorization', "Bearer {$token}")
         ->postJson("/api/v1/candidates/{$candidate->id}/approve-2nd-interview", [
             'interview_date' => now()->toDateString(),
             'comments'       => 'Interview passed',
         ])
         ->assertStatus(200);

    expect($candidate->fresh()->status)->toBe('2nd_interview_passed');
});
