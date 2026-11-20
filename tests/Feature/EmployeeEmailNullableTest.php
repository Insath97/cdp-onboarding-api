<?php

use App\Models\Department;
use App\Models\Designation;
use App\Models\Employee;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

uses(RefreshDatabase::class);

function employeeEmailDesignation(): Designation
{
    $department = Department::firstOrCreate(['code' => 'EM-DEPT'], ['name' => 'Email Test Department']);

    return Designation::firstOrCreate(
        ['code' => 'EM-DES'],
        ['department_id' => $department->id, 'name' => 'Email Test Designation', 'level' => 'entry', 'is_active' => 1]
    );
}

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'PermissionsSeeder']);

    $this->user = User::firstOrCreate(
        ['email' => 'admin@test.com'],
        [
            'name' => 'Super Admin User',
            'username' => 'superadmin_test',
            'password' => bcrypt('password'),
            'user_type' => 'admin',
            'is_active' => true,
            'can_login' => true,
        ]
    );

    $superAdminRole = Role::where('name', 'Super Admin')->where('guard_name', 'api')->first();
    if ($superAdminRole && ! $this->user->hasRole($superAdminRole)) {
        $this->user->assignRole($superAdminRole);
    }

    $this->token = JWTAuth::fromUser($this->user);

    Mail::fake();
});

it('creates a staff employee with a null email when email is not provided', function () {
    $response = $this->withHeader('Authorization', "Bearer {$this->token}")
        ->postJson('/api/v1/users', [
            'name' => 'No Email Staff',
            'user_type' => 'staff',
            'role' => 'Super Admin',
            'employee_code' => 'EMP-NOEMAIL-001',
            'id_number' => '199011111111',
            'designation_id' => employeeEmailDesignation()->id,
        ]);

    $response->assertStatus(201);

    $employee = Employee::where('employee_code', 'EMP-NOEMAIL-001')->first();
    expect($employee)->not->toBeNull()
        ->and($employee->email)->toBeNull();
});

it('creates a staff employee with the provided email', function () {
    $response = $this->withHeader('Authorization', "Bearer {$this->token}")
        ->postJson('/api/v1/users', [
            'name' => 'Email Staff',
            'user_type' => 'staff',
            'role' => 'Super Admin',
            'employee_code' => 'EMP-WITHEMAIL-001',
            'id_number' => '199022222222',
            'designation_id' => employeeEmailDesignation()->id,
            'email' => 'email.staff@test.local',
        ]);

    $response->assertStatus(201);

    $employee = Employee::where('employee_code', 'EMP-WITHEMAIL-001')->first();

    expect($employee)->not->toBeNull()
        ->and($employee->email)->toBe('email.staff@test.local');
});
