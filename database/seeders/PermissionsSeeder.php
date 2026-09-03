<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $permissions = [
            /* Access Management */
            ['name' => 'Permission Index', 'group_name' => 'Access Management Permissions'],
            ['name' => 'Permission Create', 'group_name' => 'Access Management Permissions'],
            ['name' => 'Permission Update', 'group_name' => 'Access Management Permissions'],
            ['name' => 'Permission Delete', 'group_name' => 'Access Management Permissions'],

            ['name' => 'Role Index', 'group_name' => 'Access Management Permissions'],
            ['name' => 'Role Create', 'group_name' => 'Access Management Permissions'],
            ['name' => 'Role Update', 'group_name' => 'Access Management Permissions'],
            ['name' => 'Role Delete', 'group_name' => 'Access Management Permissions'],

            /* User Management */
            ['name' => 'User Index', 'group_name' => 'User Management Permissions'],
            ['name' => 'User Create', 'group_name' => 'User Management Permissions'],
            ['name' => 'User Update', 'group_name' => 'User Management Permissions'],
            ['name' => 'User Delete', 'group_name' => 'User Management Permissions'],
            ['name' => 'User Toggle Status', 'group_name' => 'User Management Permissions'],

            /* Country Management */
            ['name' => 'Country Index', 'group_name' => 'Country Management Permissions'],
            ['name' => 'Country Create', 'group_name' => 'Country Management Permissions'],
            ['name' => 'Country Update', 'group_name' => 'Country Management Permissions'],
            ['name' => 'Country Delete', 'group_name' => 'Country Management Permissions'],
            ['name' => 'Country Toggle Status', 'group_name' => 'Country Management Permissions'],

            /* Province Management */
            ['name' => 'Province Index', 'group_name' => 'Province Management Permissions'],
            ['name' => 'Province Create', 'group_name' => 'Province Management Permissions'],
            ['name' => 'Province Update', 'group_name' => 'Province Management Permissions'],
            ['name' => 'Province Delete', 'group_name' => 'Province Management Permissions'],
            ['name' => 'Province Toggle Status', 'group_name' => 'Province Management Permissions'],

            /* Zonal Management */
            ['name' => 'Zonal Index', 'group_name' => 'Zonal Management Permissions'],
            ['name' => 'Zonal Create', 'group_name' => 'Zonal Management Permissions'],
            ['name' => 'Zonal Update', 'group_name' => 'Zonal Management Permissions'],
            ['name' => 'Zonal Delete', 'group_name' => 'Zonal Management Permissions'],
            ['name' => 'Zonal Toggle Status', 'group_name' => 'Zonal Management Permissions'],

            /* Region Management */
            ['name' => 'Region Index', 'group_name' => 'Region Management Permissions'],
            ['name' => 'Region Create', 'group_name' => 'Region Management Permissions'],
            ['name' => 'Region Update', 'group_name' => 'Region Management Permissions'],
            ['name' => 'Region Delete', 'group_name' => 'Region Management Permissions'],
            ['name' => 'Region Toggle Status', 'group_name' => 'Region Management Permissions'],

            /* Branch Management */
            ['name' => 'Branch Index', 'group_name' => 'Branch Management Permissions'],
            ['name' => 'Branch Create', 'group_name' => 'Branch Management Permissions'],
            ['name' => 'Branch Update', 'group_name' => 'Branch Management Permissions'],
            ['name' => 'Branch Delete', 'group_name' => 'Branch Management Permissions'],
            ['name' => 'Branch Toggle Status', 'group_name' => 'Branch Management Permissions'],

            /* Department Management */
            ['name' => 'Department Index', 'group_name' => 'Department Management Permissions'],
            ['name' => 'Department Create', 'group_name' => 'Department Management Permissions'],
            ['name' => 'Department Update', 'group_name' => 'Department Management Permissions'],
            ['name' => 'Department Delete', 'group_name' => 'Department Management Permissions'],
            ['name' => 'Department Toggle Status', 'group_name' => 'Department Management Permissions'],

            /* Designation Management */
            ['name' => 'Designation Index', 'group_name' => 'Designation Management Permissions'],
            ['name' => 'Designation Create', 'group_name' => 'Designation Management Permissions'],
            ['name' => 'Designation Update', 'group_name' => 'Designation Management Permissions'],
            ['name' => 'Designation Delete', 'group_name' => 'Designation Management Permissions'],
            ['name' => 'Designation Toggle Status', 'group_name' => 'Designation Management Permissions'],

            /* Group Management */
            ['name' => 'Group Index', 'group_name' => 'Group Management Permissions'],
            ['name' => 'Group Create', 'group_name' => 'Group Management Permissions'],
            ['name' => 'Group Update', 'group_name' => 'Group Management Permissions'],
            ['name' => 'Group Delete', 'group_name' => 'Group Management Permissions'],
            ['name' => 'Group Toggle Status', 'group_name' => 'Group Management Permissions'],

        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate([
                'name' => $permission['name'],
                'group_name' => $permission['group_name'],
                'guard_name' => 'api',
            ]);
        }

        $role = Role::firstOrCreate(['guard_name' => 'api', 'name' => 'Super Admin']);

        $allPermissions = Permission::all();
        $role->syncPermissions($allPermissions);
    }
}
