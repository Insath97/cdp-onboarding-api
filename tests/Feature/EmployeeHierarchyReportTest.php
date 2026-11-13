<?php

use App\Models\Branch;
use App\Models\ChannelHeadHistory;
use App\Models\ChannelWiseEmployment;
use App\Models\Country;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Employee;
use App\Models\Province;
use App\Models\Region;
use App\Models\Role;
use App\Models\User;
use App\Models\Zonal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

uses(RefreshDatabase::class);

function hierarchyReportDepartment(): Department
{
    return Department::firstOrCreate(
        ['code' => 'HR-DEPT'],
        ['name' => 'Hierarchy Report Department', 'is_active' => true]
    );
}

function hierarchyReportDesignation(string $name, string $level, string $code): Designation
{
    return Designation::firstOrCreate(
        ['code' => $code],
        [
            'department_id' => hierarchyReportDepartment()->id,
            'name' => $name,
            'level' => $level,
            'is_active' => true,
        ]
    );
}

function hierarchyReportEmployee(array $overrides = []): Employee
{
    static $sequence = 0;
    $sequence++;

    return Employee::create(array_merge([
        'f_name' => "Hierarchy{$sequence}",
        'l_name' => "Test{$sequence}",
        'full_name' => "Hierarchy{$sequence} Test{$sequence}",
        'name_with_initials' => "H{$sequence} T{$sequence}",
        'employee_code' => "HR-EMP-{$sequence}",
        'designation_id' => hierarchyReportDesignation('Officer', 'entry', 'HR-OFF')->id,
        'employee_type' => 'permanent',
        'id_type' => 'nic',
        'id_number' => "HR-NIC-{$sequence}",
        'date_of_birth' => '1990-01-01',
        'email' => "hierarchy-{$sequence}@test.local",
        'phone_primary' => '0771234567',
        'is_active' => true,
    ], $overrides));
}

function hierarchyReportBranch(string $code, string $name): Branch
{
    $country = Country::firstOrCreate(['code' => 'HR-CTY'], ['name' => 'Hierarchy Land']);
    $province = Province::firstOrCreate(
        ['code' => 'HR-PRV'],
        ['name' => 'Hierarchy Province', 'country_id' => $country->id, 'is_active' => true]
    );
    $zonal = Zonal::firstOrCreate(
        ['code' => 'HR-ZNL'],
        ['name' => 'Hierarchy Zone', 'province_id' => $province->id, 'is_active' => true]
    );
    $region = Region::firstOrCreate(
        ['code' => 'HR-REG'],
        ['name' => 'Hierarchy Region', 'zonal_id' => $zonal->id, 'is_active' => true]
    );

    return Branch::firstOrCreate(
        ['code' => $code],
        [
            'name' => $name,
            'province_id' => $province->id,
            'zone_id' => $zonal->id,
            'region_id' => $region->id,
            'address_line1' => '123 Test Street',
            'city' => 'Colombo',
            'phone_primary' => '0111234567',
            'opening_date' => '2020-01-01',
            'branch_type' => 'main',
            'is_active' => true,
        ]
    );
}

function hierarchyReportFixture(): array
{
    $directorDesignation = hierarchyReportDesignation('Director', 'Director', 'HR-DIR');
    $managerDesignation = hierarchyReportDesignation('Manager', 'Manager', 'HR-MGR');
    $officerDesignation = hierarchyReportDesignation('Officer', 'entry', 'HR-OFF');

    $branchOne = hierarchyReportBranch('HR-BR1', 'Hierarchy Branch One');
    $branchTwo = hierarchyReportBranch('HR-BR2', 'Hierarchy Branch Two');

    $director = hierarchyReportEmployee([
        'designation_id' => $directorDesignation->id,
        'full_name' => 'Alpha Director',
        'employee_code' => 'HR-DIR-001',
        'id_number' => 'HR-NIC-DIR-001',
        'email' => 'alpha.director@test.local',
    ]);

    $manager = hierarchyReportEmployee([
        'designation_id' => $managerDesignation->id,
        'reporting_manager_id' => $director->id,
        'full_name' => 'Beta Manager',
        'employee_code' => 'HR-MGR-001',
        'id_number' => 'HR-NIC-MGR-001',
        'email' => 'beta.manager@test.local',
    ]);

    $officerOne = hierarchyReportEmployee([
        'designation_id' => $officerDesignation->id,
        'reporting_manager_id' => $manager->id,
        'branch_id' => $branchOne->id,
        'full_name' => 'Gamma Officer',
        'employee_code' => 'HR-OFC-001',
        'id_number' => 'HR-NIC-OFC-001',
        'email' => 'gamma.officer@test.local',
    ]);

    $officerTwo = hierarchyReportEmployee([
        'designation_id' => $officerDesignation->id,
        'reporting_manager_id' => $director->id,
        'branch_id' => $branchTwo->id,
        'full_name' => 'Delta Officer',
        'employee_code' => 'HR-OFC-002',
        'id_number' => 'HR-NIC-OFC-002',
        'email' => 'delta.officer@test.local',
    ]);

    return compact('director', 'manager', 'officerOne', 'officerTwo', 'branchOne', 'branchTwo', 'officerDesignation');
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
});

it('requires authentication', function () {
    $this->getJson('/api/v1/reports/employee-hierarchy')
        ->assertStatus(401);
});

it('forbids users without report view permission', function () {
    $plainUser = User::firstOrCreate(
        ['email' => 'noperm@test.com'],
        [
            'name' => 'No Permission User',
            'username' => 'noperm_test',
            'password' => bcrypt('password'),
            'user_type' => 'staff',
            'is_active' => true,
            'can_login' => true,
        ]
    );

    $token = JWTAuth::fromUser($plainUser);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/reports/employee-hierarchy')
        ->assertStatus(403);
});

it('returns the nested employee hierarchy tree', function () {
    hierarchyReportFixture();

    $response = $this->withHeader('Authorization', "Bearer {$this->token}")
        ->getJson('/api/v1/reports/employee-hierarchy');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'status',
            'message',
            'data',
            'meta' => ['total_employees_count', 'total_users_count', 'matched_count', 'filters_applied'],
        ]);

    $data = $response->json('data');

    expect($data)->toHaveCount(1);
    expect($data[0]['full_name'])->toBe('Alpha Director');
    expect($data[0]['total_subordinates_count'])->toBe(3);
    expect($data[0]['children'])->toHaveCount(2);

    expect($data[0]['children'][0]['full_name'])->toBe('Beta Manager');
    expect($data[0]['children'][0]['total_subordinates_count'])->toBe(1);
    expect($data[0]['children'][1]['full_name'])->toBe('Delta Officer');
    expect($data[0]['children'][1]['total_subordinates_count'])->toBe(0);

    expect($data[0]['children'][0]['children'][0]['full_name'])->toBe('Gamma Officer');
    expect($data[0]['children'][0]['children'][0]['children'])->toBeEmpty();

    expect($response->json('meta.total_employees_count'))->toBe(4);
    expect($response->json('meta.matched_count'))->toBe(4);
});

it('includes user accounts on hierarchy nodes', function () {
    $fixture = hierarchyReportFixture();

    User::create([
        'name' => 'Gamma Officer User',
        'username' => 'gamma_officer_rt',
        'email' => 'gamma.officer.user@test.local',
        'password' => bcrypt('password'),
        'user_type' => 'staff',
        'employee_id' => $fixture['officerOne']->id,
        'is_active' => true,
        'can_login' => true,
    ]);

    $response = $this->withHeader('Authorization', "Bearer {$this->token}")
        ->getJson('/api/v1/reports/employee-hierarchy');

    $response->assertStatus(200);

    expect($response->json('meta.total_users_count'))->toBe(1);

    $node = $response->json('data.0.children.0.children.0');
    expect($node['full_name'])->toBe('Gamma Officer');
    expect($node['user']['username'])->toBe('gamma_officer_rt');
});

it('filters by branch while keeping ancestors in the tree', function () {
    $fixture = hierarchyReportFixture();

    $response = $this->withHeader('Authorization', "Bearer {$this->token}")
        ->getJson("/api/v1/reports/employee-hierarchy?branch_id={$fixture['branchOne']->id}");

    $response->assertStatus(200);

    $data = $response->json('data');

    expect($data)->toHaveCount(1);
    expect($data[0]['full_name'])->toBe('Alpha Director');
    expect($data[0]['matches_filter'])->toBeFalse();
    expect($data[0]['children'][0]['full_name'])->toBe('Beta Manager');
    expect($data[0]['children'][0]['matches_filter'])->toBeFalse();
    expect($data[0]['children'][0]['children'][0]['full_name'])->toBe('Gamma Officer');
    expect($data[0]['children'][0]['children'][0]['matches_filter'])->toBeTrue();

    expect($data[0]['children'])->toHaveCount(1);
    expect($response->json('meta.matched_count'))->toBe(1);
    expect($response->json('meta.total_employees_count'))->toBe(3);
});

it('filters by designation', function () {
    $fixture = hierarchyReportFixture();

    $response = $this->withHeader('Authorization', "Bearer {$this->token}")
        ->getJson("/api/v1/reports/employee-hierarchy?designation_id={$fixture['officerDesignation']->id}");

    $response->assertStatus(200);

    $data = $response->json('data');

    expect($data)->toHaveCount(1);
    expect($data[0]['full_name'])->toBe('Alpha Director');
    expect($data[0]['children'])->toHaveCount(2);
    expect($data[0]['children'][0]['full_name'])->toBe('Beta Manager');
    expect($data[0]['children'][1]['full_name'])->toBe('Delta Officer');
    expect($data[0]['children'][1]['matches_filter'])->toBeTrue();

    expect($response->json('meta.matched_count'))->toBe(2);
    expect($response->json('meta.total_employees_count'))->toBe(4);
});

it('filters by channel scope using the current channel head', function () {
    $fixture = hierarchyReportFixture();

    $outsider = hierarchyReportEmployee([
        'full_name' => 'Omega Outsider',
        'employee_code' => 'HR-OUT-001',
        'id_number' => 'HR-NIC-OUT-001',
        'email' => 'omega.outsider@test.local',
    ]);

    $channel = ChannelWiseEmployment::create([
        'name' => 'Hierarchy Channel Test',
        'description' => 'Channel for hierarchy report test',
        'is_active' => true,
    ]);

    ChannelHeadHistory::create([
        'channel_wise_employment_id' => $channel->id,
        'employee_id' => $fixture['director']->id,
        'effective_from' => '2026-01-01',
        'changed_by' => $this->user->id,
    ]);

    $response = $this->withHeader('Authorization', "Bearer {$this->token}")
        ->getJson("/api/v1/reports/employee-hierarchy?channel_id={$channel->id}");

    $response->assertStatus(200);

    $data = $response->json('data');

    expect($data)->toHaveCount(1);
    expect($data[0]['full_name'])->toBe('Alpha Director');
    expect($response->json('meta.total_employees_count'))->toBe(4);
    expect($response->json('meta.matched_count'))->toBe(4);

    $names = json_encode($data);
    expect($names)->not->toContain('Omega Outsider');
});

it('returns an empty tree when no employees match the filters', function () {
    hierarchyReportFixture();

    $response = $this->withHeader('Authorization', "Bearer {$this->token}")
        ->getJson('/api/v1/reports/employee-hierarchy?branch_id=999999');

    $response->assertStatus(200)
        ->assertJson([
            'status' => 'success',
            'message' => 'Employee hierarchy report retrieved successfully.',
            'data' => [],
        ]);

    expect($response->json('meta.matched_count'))->toBe(0);
    expect($response->json('meta.total_employees_count'))->toBe(0);
});

it('returns an empty tree when the selected channel has no active head', function () {
    hierarchyReportFixture();

    $channel = ChannelWiseEmployment::create([
        'name' => 'Headless Channel Test',
        'is_active' => true,
    ]);

    $response = $this->withHeader('Authorization', "Bearer {$this->token}")
        ->getJson("/api/v1/reports/employee-hierarchy?channel_id={$channel->id}");

    $response->assertStatus(200)
        ->assertJson([
            'status' => 'success',
            'message' => 'No active channel head found for the selected channel.',
            'data' => [],
        ]);
});

it('searches employees within the hierarchy', function () {
    hierarchyReportFixture();

    $response = $this->withHeader('Authorization', "Bearer {$this->token}")
        ->getJson('/api/v1/reports/employee-hierarchy?search=HR-OFC-001');

    $response->assertStatus(200);

    $data = $response->json('data');

    expect($data)->toHaveCount(1);
    expect($data[0]['full_name'])->toBe('Alpha Director');
    expect($data[0]['children'][0]['full_name'])->toBe('Beta Manager');
    expect($data[0]['children'][0]['children'][0]['full_name'])->toBe('Gamma Officer');
    expect($data[0]['children'])->toHaveCount(1);

    expect($response->json('meta.matched_count'))->toBe(1);
    expect($response->json('meta.total_employees_count'))->toBe(3);
});

it('excludes inactive employees by default and includes them when requested', function () {
    $fixture = hierarchyReportFixture();

    $fixture['manager']->update(['is_active' => false]);

    $defaultResponse = $this->withHeader('Authorization', "Bearer {$this->token}")
        ->getJson('/api/v1/reports/employee-hierarchy');

    $defaultResponse->assertStatus(200);
    expect($defaultResponse->json('meta.total_employees_count'))->toBe(3);

    $inactiveResponse = $this->withHeader('Authorization', "Bearer {$this->token}")
        ->getJson('/api/v1/reports/employee-hierarchy?is_active=0');

    $inactiveResponse->assertStatus(200);
    expect($inactiveResponse->json('meta.total_employees_count'))->toBe(1);
    expect($inactiveResponse->json('data.0.full_name'))->toBe('Beta Manager');
});
