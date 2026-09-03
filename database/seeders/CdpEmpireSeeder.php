<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Application;
use App\Models\Module;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;

class CdpEmpireSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Applications
        $credix = Application::updateOrCreate(
            ['code' => 'credix'],
            ['name' => 'Credix', 'description' => 'Loans & Pawning Portal', 'is_active' => true]
        );

        $hrms = Application::updateOrCreate(
            ['code' => 'hrms'],
            ['name' => 'HRMS', 'description' => 'Human Resource Management System', 'is_active' => true]
        );

        $orbit = Application::updateOrCreate(
            ['code' => 'orbit'],
            ['name' => 'Orbit', 'description' => 'Orbit Core System', 'is_active' => true]
        );

        // 2. Modules for Credix
        $customers = Module::updateOrCreate(
            ['application_id' => $credix->id, 'code' => 'customers'],
            ['name' => 'Customers', 'description' => 'Customer profiles management']
        );

        $loans = Module::updateOrCreate(
            ['application_id' => $credix->id, 'code' => 'loans'],
            ['name' => 'Loans', 'description' => 'Loan processing module']
        );

        $reports = Module::updateOrCreate(
            ['application_id' => $credix->id, 'code' => 'reports'],
            ['name' => 'Reports', 'description' => 'Reports module']
        );

        // Modules for HRMS
        $employees = Module::updateOrCreate(
            ['application_id' => $hrms->id, 'code' => 'employees'],
            ['name' => 'Employees', 'description' => 'Employee details module']
        );

        // 3. Permissions for Credix - Customers
        $pCustomerView = Permission::updateOrCreate(
            ['name' => 'customer.view', 'guard_name' => 'api'],
            ['group_name' => 'Credix Customers', 'module_id' => $customers->id, 'application_id' => $credix->id]
        );
        $pCustomerCreate = Permission::updateOrCreate(
            ['name' => 'customer.create', 'guard_name' => 'api'],
            ['group_name' => 'Credix Customers', 'module_id' => $customers->id, 'application_id' => $credix->id]
        );
        $pCustomerEdit = Permission::updateOrCreate(
            ['name' => 'customer.edit', 'guard_name' => 'api'],
            ['group_name' => 'Credix Customers', 'module_id' => $customers->id, 'application_id' => $credix->id]
        );

        // Permissions for Credix - Loans
        $pLoanView = Permission::updateOrCreate(
            ['name' => 'loan.view', 'guard_name' => 'api'],
            ['group_name' => 'Credix Loans', 'module_id' => $loans->id, 'application_id' => $credix->id]
        );
        $pLoanCreate = Permission::updateOrCreate(
            ['name' => 'loan.create', 'guard_name' => 'api'],
            ['group_name' => 'Credix Loans', 'module_id' => $loans->id, 'application_id' => $credix->id]
        );
        $pLoanApprove = Permission::updateOrCreate(
            ['name' => 'loan.approve', 'guard_name' => 'api'],
            ['group_name' => 'Credix Loans', 'module_id' => $loans->id, 'application_id' => $credix->id]
        );

        // Permissions for HRMS - Employees
        $pEmployeeView = Permission::updateOrCreate(
            ['name' => 'employee.view', 'guard_name' => 'api'],
            ['group_name' => 'HRMS Employees', 'module_id' => $employees->id, 'application_id' => $hrms->id]
        );
        $pEmployeeCreate = Permission::updateOrCreate(
            ['name' => 'employee.create', 'guard_name' => 'api'],
            ['group_name' => 'HRMS Employees', 'module_id' => $employees->id, 'application_id' => $hrms->id]
        );

        // 4. Roles
        // Credix Loan Officer
        $rLoanOfficer = Role::updateOrCreate(
            ['name' => 'Loan Officer', 'guard_name' => 'api', 'application_id' => $credix->id]
        );
        $rLoanOfficer->syncPermissions([$pCustomerView, $pCustomerCreate, $pLoanView, $pLoanCreate]);

        // HRMS Employee
        $rHrmsEmployee = Role::updateOrCreate(
            ['name' => 'Employee', 'guard_name' => 'api', 'application_id' => $hrms->id]
        );
        $rHrmsEmployee->syncPermissions([$pEmployeeView]);

        // 5. Create user Mohamed
        $mohamed = User::updateOrCreate(
            ['email' => 'mohamed@cdp.lk', 'username' => 'mohamed'],
            [
                'name' => 'Mohamed',
                'password' => bcrypt('password'),
                'is_active' => true,
                'can_login' => true,
            ]
        );

        // Assign global role to mohamed
        $rStaff = Role::updateOrCreate(
            ['name' => 'Staff', 'guard_name' => 'api', 'application_id' => null]
        );
        $mohamed->assignRole($rStaff);

        // Assign Mohamed to Credix and HRMS applications
        $mohamed->applications()->sync([$credix->id, $hrms->id]);

        // Assign application roles
        $mohamed->syncRolesForApplication([$rLoanOfficer->id], $credix->id);
        $mohamed->syncRolesForApplication([$rHrmsEmployee->id], $hrms->id);

        $this->command->info('CDP Empire seeder executed successfully!');
    }
}
