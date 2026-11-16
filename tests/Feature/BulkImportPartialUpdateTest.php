<?php

use App\Models\Department;
use App\Models\Designation;
use App\Models\Employee;
use App\Services\BulkImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;

uses(RefreshDatabase::class);

function seedDesignation(): Designation
{
    $department = Department::create(['name' => 'Ops', 'code' => 'OPS']);

    return Designation::create(['name' => 'Exec', 'code' => 'EXE', 'department_id' => $department->id, 'level' => 'senior', 'is_active' => 1]);
}

function seedEmployee(string $code, Designation $designation): Employee
{
    return Employee::create([
        'f_name' => 'Test', 'l_name' => 'Employee', 'full_name' => 'Test Employee',
        'name_with_initials' => 'T. Employee',
        'employee_code' => $code, 'designation_id' => $designation->id,
        'id_number' => '1990'.str_pad(crc32($code) % 1000000000, 9, '0', STR_PAD_LEFT),
        'email' => strtolower($code).'@cdp.lk', 'phone_primary' => '0771234567',
        'employee_type' => 'permanent', 'id_type' => 'nic',
        'date_of_birth' => '1990-05-20',
    ]);
}

function makeImportCsv(string $table, string $content): UploadedFile
{
    $path = storage_path("framework/testing/import_{$table}_".uniqid().'.csv');
    file_put_contents($path, $content);

    return new UploadedFile($path, basename($path), 'text/csv', null, true);
}

it('updates only provided columns without wiping required fields', function () {
    $designation = seedDesignation();
    seedEmployee('EMP001', $designation);

    $csv = "employee_code,reporting_manager_id\nEMP001,\n";
    $results = app(BulkImportService::class)->import(makeImportCsv('employees', $csv), 'employees');

    $employee = Employee::where('employee_code', 'EMP001')->first();

    expect($results['failed'])->toBe(0)
        ->and($employee->email)->toBe('emp001@cdp.lk')
        ->and($employee->id_number)->not->toBeNull()
        ->and($results['errors'])->toBeEmpty();
});

it('resolves reporting manager by employee_code even when the manager appears later in the file', function () {
    $designation = seedDesignation();
    seedEmployee('EMP001', $designation);
    seedEmployee('EMP002', $designation);

    $csv = "employee_code,reporting_manager_id\n"
        ."EMP002,EMP001\n";
    $results = app(BulkImportService::class)->import(makeImportCsv('employees', $csv), 'employees');

    $child = Employee::where('employee_code', 'EMP002')->first();
    $manager = Employee::where('employee_code', 'EMP001')->first();

    expect($results['imported'])->toBe(1)
        ->and($results['failed'])->toBe(0)
        ->and($child->reporting_manager_id)->toBe($manager->id);
});

it('accepts reporting_manager_id as a database id or employee code', function () {
    $designation = seedDesignation();
    $manager = seedEmployee('MGR001', $designation);
    seedEmployee('EMP010', $designation);
    seedEmployee('EMP011', $designation);

    $csv = "employee_code,reporting_manager_id\n"
        ."EMP010,{$manager->id}\n"
        ."EMP011,MGR001\n";
    $results = app(BulkImportService::class)->import(makeImportCsv('employees', $csv), 'employees');

    expect($results['failed'])->toBe(0)
        ->and(Employee::where('employee_code', 'EMP010')->first()->reporting_manager_id)->toBe($manager->id)
        ->and(Employee::where('employee_code', 'EMP011')->first()->reporting_manager_id)->toBe($manager->id);
});

it('reports unknown manager codes and self-references as row errors', function () {
    $designation = seedDesignation();
    seedEmployee('EMP020', $designation);
    seedEmployee('EMP021', $designation);

    $csv = "employee_code,reporting_manager_id\n"
        ."EMP020,NOPE123\n"
        ."EMP021,EMP021\n";
    $results = app(BulkImportService::class)->import(makeImportCsv('employees', $csv), 'employees');

    expect($results['failed'])->toBe(2)
        ->and($results['errors'][0]['error'])->toContain('does not exist')
        ->and($results['errors'][1]['error'])->toContain('cannot reference itself');
});

it('matches rows by id and updates the reporting manager', function () {
    $designation = seedDesignation();
    $manager = seedEmployee('MGR001', $designation);
    $employee = seedEmployee('EMP030', $designation);

    $csv = "id,reporting_manager_id\n"
        ."{$employee->id},MGR001\n";
    $results = app(BulkImportService::class)->import(makeImportCsv('employees', $csv), 'employees');

    $employee->refresh();

    expect($results['imported'])->toBe(1)
        ->and($results['failed'])->toBe(0)
        ->and($employee->reporting_manager_id)->toBe($manager->id)
        ->and($employee->email)->toBe('emp030@cdp.lk');
});
