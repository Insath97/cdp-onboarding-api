<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Candidate;
use App\Models\ChannelWiseEmployment;
use App\Models\Country;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Employee;
use App\Models\Group;
use App\Models\Province;
use App\Models\Region;
use App\Models\User;
use App\Models\Zonal;
use App\Traits\ActivityLogTrait;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class BulkImportService
{
    use ActivityLogTrait;

    /**
     * Get configuration for all importable tables in the onboarding system using Database IDs.
     */
    public function getImportableConfig(): array
    {
        return [
            'countries' => [
                'model' => Country::class,
                'unique_key' => 'code',
                'fillable' => ['name', 'code', 'description', 'is_active'],
            ],
            'provinces' => [
                'model' => Province::class,
                'unique_key' => 'code',
                'dependencies' => [
                    'country_id' => ['model' => Country::class, 'foreign_key' => 'country_id'],
                ],
                'fillable' => ['country_id', 'name', 'code', 'is_active'],
            ],
            'zonals' => [
                'model' => Zonal::class,
                'unique_key' => 'code',
                'dependencies' => [
                    'province_id' => ['model' => Province::class, 'foreign_key' => 'province_id'],
                ],
                'fillable' => ['province_id', 'name', 'code', 'is_active'],
            ],
            'regions' => [
                'model' => Region::class,
                'unique_key' => 'code',
                'dependencies' => [
                    'zonal_id' => ['model' => Zonal::class, 'foreign_key' => 'zonal_id'],
                ],
                'fillable' => ['zonal_id', 'name', 'code', 'is_active'],
            ],
            'groups' => [
                'model' => Group::class,
                'unique_key' => 'code',
                'fillable' => ['name', 'code', 'description', 'is_active'],
            ],
            'branches' => [
                'model' => Branch::class,
                'unique_key' => 'code',
                'dependencies' => [
                    'province_id' => ['model' => Province::class, 'foreign_key' => 'province_id'],
                    'zone_id' => ['model' => Zonal::class, 'foreign_key' => 'zone_id'],
                    'region_id' => ['model' => Region::class, 'foreign_key' => 'region_id'],
                    'group_id' => ['model' => Group::class, 'foreign_key' => 'group_id'],
                ],
                'fillable' => [
                    'name', 'code', 'group_id', 'address_line1', 'address_line2', 'city', 'postal_code',
                    'zone_id', 'region_id', 'province_id', 'phone_primary', 'phone_secondary', 'email',
                    'fax', 'opening_date', 'branch_type', 'latitude', 'longitude', 'is_active', 'is_head_office',
                ],
            ],
            'departments' => [
                'model' => Department::class,
                'unique_key' => 'code',
                'dependencies' => [
                    'head_id' => ['model' => Employee::class, 'foreign_key' => 'head_id'],
                ],
                'fillable' => ['name', 'code', 'description', 'head_id', 'is_active'],
            ],
            'designations' => [
                'model' => Designation::class,
                'unique_key' => 'code',
                'dependencies' => [
                    'department_id' => ['model' => Department::class, 'foreign_key' => 'department_id'],
                ],
                'fillable' => ['name', 'code', 'department_id', 'level', 'order_weight', 'description', 'is_active'],
            ],
            'channel_wise_employments' => [
                'model' => ChannelWiseEmployment::class,
                'unique_key' => 'name',
                'fillable' => ['name', 'description', 'is_active'],
            ],
            'employees' => [
                'model' => Employee::class,
                'unique_key' => 'employee_code',
                'dependencies' => [
                    'province_id' => ['model' => Province::class, 'foreign_key' => 'province_id'],
                    'zonal_id' => ['model' => Zonal::class, 'foreign_key' => 'zonal_id'],
                    'region_id' => ['model' => Region::class, 'foreign_key' => 'region_id'],
                    'branch_id' => ['model' => Branch::class, 'foreign_key' => 'branch_id'],
                    'department_id' => ['model' => Department::class, 'foreign_key' => 'department_id'],
                    'designation_id' => ['model' => Designation::class, 'foreign_key' => 'designation_id'],
                    'reporting_manager_id' => ['model' => Employee::class, 'foreign_key' => 'reporting_manager_id'],
                ],
                'fillable' => [
                    'f_name', 'l_name', 'full_name', 'name_with_initials', 'employee_code',
                    'reporting_manager_id', 'province_id', 'region_id', 'zonal_id', 'branch_id',
                    'department_id', 'designation_id', 'employee_type', 'id_type', 'id_number',
                    'date_of_birth', 'email', 'phone', 'address_line_1', 'city', 'state', 'country',
                    'postal_code', 'phone_primary', 'phone_secondary', 'have_whatsapp', 'whatsapp_number',
                    'start_date', 'end_date', 'joined_at', 'is_active',
                ],
            ],
            'candidates' => [
                'model' => Candidate::class,
                'unique_key' => 'nic_number',
                'dependencies' => [
                    'designation_id' => ['model' => Designation::class, 'foreign_key' => 'designation_id'],
                    'uploaded_by' => ['model' => User::class, 'foreign_key' => 'uploaded_by'],
                    'channel_head_employee_id' => ['model' => Employee::class, 'foreign_key' => 'channel_head_employee_id'],
                ],
                'fillable' => [
                    'first_name', 'last_name', 'nic_number', 'phone_number', 'secondary_number',
                    'designation_id', 'expected_salary', 'status', 'uploaded_by', 'uploader_comments',
                    'submitted_at', 'channel_head_employee_id', 'stage_updated_at',
                ],
            ],
            'users' => [
                'model' => User::class,
                'unique_key' => 'username',
                'dependencies' => [
                    'employee_id' => ['model' => Employee::class, 'foreign_key' => 'employee_id'],
                ],
                'special_fields' => ['password', 'role'],
                'fillable' => ['name', 'username', 'email', 'user_type', 'employee_id', 'is_active', 'can_login'],
            ],
        ];
    }

    /**
     * Import data from CSV/Excel file for a specific table.
     */
    public function import(UploadedFile $file, string $table): array
    {
        $results = [
            'total' => 0,
            'imported' => 0,
            'failed' => 0,
            'errors' => [],
        ];

        $config = $this->getImportableConfig()[$table] ?? null;
        if (! $config) {
            return array_merge($results, ['errors' => ["Unsupported table: {$table}"]]);
        }

        $handle = fopen($file->getRealPath(), 'r');

        // Read headers
        $headers = fgetcsv($handle);
        if (! $headers) {
            fclose($handle);

            return array_merge($results, ['errors' => ['Empty or invalid CSV file.']]);
        }

        // Clean BOM and trim headers
        $headers = array_map(function ($header) {
            return trim($header, "\xEF\xBB\xBF");
        }, $headers);
        $headers = array_map('trim', $headers);

        $rowNumber = 1;
        while (($rowData = fgetcsv($handle)) !== false) {
            $rowNumber++;
            $results['total']++;

            if (count($headers) !== count($rowData)) {
                $results['failed']++;
                $results['errors'][] = [
                    'row' => $rowNumber,
                    'error' => 'Column count mismatch. Expected ' . count($headers) . ', got ' . count($rowData),
                ];

                continue;
            }

            $data = array_combine($headers, $rowData);
            $data = $this->cleanData($data);

            // Preprocess specific fields
            $data = $this->preprocessRowData($data);

            DB::beginTransaction();
            try {
                $this->processGenericRow($config, $data);
                DB::commit();
                $results['imported']++;
            } catch (\Throwable $e) {
                DB::rollBack();
                $results['failed']++;
                $results['errors'][] = [
                    'row' => $rowNumber,
                    'error' => $e->getMessage(),
                ];
                $this->logActivity(
                    'BULK_IMPORT_ROW_FAILED',
                    'BulkImport',
                    "Import failed for table {$table} at row {$rowNumber}: " . $e->getMessage(),
                    ['table' => $table, 'row' => $rowNumber, 'error' => $e->getMessage()],
                    'error'
                );
            }
        }

        fclose($handle);

        $this->logActivity(
            'BULK_IMPORT_SUCCESS',
            'BulkImport',
            "Bulk import completed for table {$table}. Total: {$results['total']}, Imported: {$results['imported']}, Failed: {$results['failed']}",
            ['table' => $table, 'results' => $results]
        );

        return $results;
    }

    /**
     * Clean and sanitize row input data.
     */
    protected function cleanData(array $data): array
    {
        $cleaned = [];
        foreach ($data as $key => $value) {
            if ($value === null || $value === '') {
                $cleaned[$key] = null;
            } else {
                $cleaned[$key] = trim($value);
            }
        }

        return $cleaned;
    }

    /**
     * Common Row Data Preprocessing.
     */
    protected function preprocessRowData(array $data): array
    {
        // Date parsing
        $dateFields = [
            'opening_date', 'date_of_birth', 'start_date', 'end_date',
            'joined_at', 'submitted_at', 'stage_updated_at',
        ];
        foreach ($dateFields as $field) {
            if (! empty($data[$field])) {
                $data[$field] = $this->parseDate($data[$field]);
            }
        }

        // Booleans
        $booleanFields = ['is_active', 'can_login', 'is_head_office', 'have_whatsapp'];
        foreach ($booleanFields as $field) {
            if (array_key_exists($field, $data)) {
                $data[$field] = $this->parseBoolean($data[$field]);
            }
        }

        // Phone numbers
        $phoneFields = ['phone_primary', 'phone_secondary', 'phone', 'phone_number', 'secondary_number', 'whatsapp_number', 'fax'];
        foreach ($phoneFields as $field) {
            if (! empty($data[$field])) {
                $data[$field] = $this->cleanPhoneNumber($data[$field]);
            }
        }

        return $data;
    }

    /**
     * Parse boolean values.
     */
    protected function parseBoolean($value): bool
    {
        if ($value === null) {
            return true;
        }
        $val = strtolower(trim((string) $value));

        return in_array($val, ['1', 'true', 'yes', 'y', 'on'], true);
    }

    /**
     * Parse dates to Y-m-d (or Y-m-d H:i:s) format.
     */
    protected function parseDate($date): ?string
    {
        if (empty($date)) {
            return null;
        }

        $date = trim($date);

        if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})(?:\s+(\d{1,2}):(\d{2})(?::(\d{2}))?)?$/', $date, $matches)) {
            $day = str_pad($matches[1], 2, '0', STR_PAD_LEFT);
            $month = str_pad($matches[2], 2, '0', STR_PAD_LEFT);
            $year = $matches[3];

            if (checkdate((int) $month, (int) $day, (int) $year)) {
                $result = "{$year}-{$month}-{$day}";
                if (! empty($matches[4])) {
                    $hour = str_pad($matches[4], 2, '0', STR_PAD_LEFT);
                    $minute = str_pad($matches[5], 2, '0', STR_PAD_LEFT);
                    $second = ! empty($matches[6]) ? str_pad($matches[6], 2, '0', STR_PAD_LEFT) : '00';
                    $result .= " {$hour}:{$minute}:{$second}";
                }

                return $result;
            }
        }

        try {
            $parsed = Carbon::parse($date);
            $hasTime = in_array($parsed->format('H:i:s'), ['00:00:00'], true) === false;

            return $parsed->format($hasTime ? 'Y-m-d H:i:s' : 'Y-m-d');
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Clean phone numbers.
     */
    protected function cleanPhoneNumber($phone): ?string
    {
        if (empty($phone)) {
            return null;
        }

        return preg_replace('/[^0-9+]/', '', $phone);
    }

    /**
     * Process single row using configuration and database ID validation.
     */
    protected function processGenericRow(array $config, array $data): void
    {
        $modelClass = $config['model'];
        $uniqueKeyField = $config['unique_key'];

        // Fallback unique key resolution
        if (! isset($data[$uniqueKeyField]) || $data[$uniqueKeyField] === '') {
            if (isset($data['id']) && $data['id'] !== '') {
                $uniqueKeyField = 'id';
            }
        }

        if (! isset($data[$uniqueKeyField])) {
            throw new \Exception("Missing unique identifier '{$uniqueKeyField}' in row data.");
        }

        // Validate Foreign Key Dependencies (Database IDs)
        if (isset($config['dependencies'])) {
            foreach ($config['dependencies'] as $foreignKey => $dep) {
                if (! empty($data[$foreignKey])) {
                    $idVal = (int) $data[$foreignKey];
                    $exists = $dep['model']::where('id', $idVal)->exists();
                    if (! $exists) {
                        throw new \Exception("Invalid foreign key reference: {$foreignKey} with ID '{$idVal}' does not exist in database.");
                    }
                }
            }
        }

        // Special handling for Users
        if ($modelClass === User::class) {
            if (! empty($data['password'])) {
                $data['password'] = Hash::make($data['password']);
            } else {
                $data['password'] = Hash::make($data['username']); // Default fallback
            }
            $roleName = $data['role'] ?? null;
            unset($data['role']);

            $user = User::updateOrCreate([$uniqueKeyField => $data[$uniqueKeyField]], $data);

            if ($roleName) {
                $user->syncRoles([$roleName]);
            }

            return;
        }

        // Allowed fields
        $allowedFields = $config['fillable'];
        if (! in_array($uniqueKeyField, $allowedFields, true)) {
            $allowedFields[] = $uniqueKeyField;
        }

        // Filter row data
        $filteredData = array_intersect_key($data, array_flip($allowedFields));

        try {
            $modelClass::updateOrCreate(
                [$uniqueKeyField => $filteredData[$uniqueKeyField]],
                $filteredData
            );
        } catch (\Illuminate\Database\QueryException $e) {
            if ($e->getCode() === '23000') {
                throw new \Exception('Database foreign key integrity constraint violation. Please check that referenced parent IDs exist.');
            }
            throw $e;
        }
    }

    /**
     * Get list of importable tables for dynamic select box selection.
     */
    public function getImportableTables(): array
    {
        $configs = $this->getImportableConfig();
        $list = [];

        foreach ($configs as $table => $config) {
            $headers = $config['fillable'];
            if (isset($config['special_fields'])) {
                $headers = array_merge($headers, $config['special_fields']);
            }

            $list[] = [
                'table' => $table,
                'name' => ucwords(str_replace('_', ' ', $table)),
                'headers' => array_values(array_unique($headers)),
                'unique_key' => $config['unique_key'],
            ];
        }

        return $list;
    }

    /**
     * Get template data (headers and actual sample row) for a specific table using IDs.
     */
    public function getTemplateData(string $table): ?array
    {
        $configs = $this->getImportableConfig();
        if (! isset($configs[$table])) {
            return null;
        }

        $config = $configs[$table];
        $headers = $config['fillable'];
        if (isset($config['special_fields'])) {
            $headers = array_merge($headers, $config['special_fields']);
        }
        if (! in_array($config['unique_key'], $headers, true)) {
            $headers[] = $config['unique_key'];
        }

        $headers = array_values(array_unique($headers));

        // Realistic sample values tailored for CDP Onboarding entities
        $sampleData = [
            'name' => 'Sample Name',
            'code' => 'CODE001',
            'description' => 'Sample description.',
            'is_active' => '1',
            'country_id' => '1',
            'province_id' => '1',
            'zonal_id' => '1',
            'zone_id' => '1',
            'region_id' => '1',
            'group_id' => '1',
            'branch_id' => '1',
            'department_id' => '1',
            'designation_id' => '1',
            'head_id' => '1',
            'reporting_manager_id' => '1',
            'employee_id' => '1',
            'uploaded_by' => '1',
            'channel_head_employee_id' => '1',
            'level' => 'senior',
            'order_weight' => '30',
            'address_line1' => '123 Galle Road',
            'address_line2' => 'Level 4',
            'address_line_1' => '123 Galle Road',
            'city' => 'Colombo',
            'state' => 'Western Province',
            'country' => 'Sri Lanka',
            'postal_code' => '00300',
            'phone_primary' => '+94771234567',
            'phone_secondary' => '+94777654321',
            'phone' => '+94112345678',
            'phone_number' => '+94771234567',
            'secondary_number' => '+94777654321',
            'whatsapp_number' => '+94771234567',
            'have_whatsapp' => '1',
            'email' => 'sample.user@cdp.lk',
            'fax' => '+94112345679',
            'opening_date' => '2026-01-15',
            'branch_type' => 'main',
            'latitude' => '6.9271',
            'longitude' => '79.8612',
            'is_head_office' => '0',
            'f_name' => 'Kasun',
            'l_name' => 'Perera',
            'full_name' => 'Kasun Perera',
            'name_with_initials' => 'K. Perera',
            'first_name' => 'Kasun',
            'last_name' => 'Perera',
            'employee_code' => 'EMP001',
            'employee_type' => 'permanent',
            'id_type' => 'nic',
            'id_number' => '199012345678',
            'nic_number' => '199012345678',
            'date_of_birth' => '1990-05-20',
            'start_date' => '2026-01-01',
            'end_date' => '',
            'joined_at' => '2026-01-01 09:00:00',
            'expected_salary' => '120000.00',
            'status' => 'pending',
            'uploader_comments' => 'CV submitted via onboarding portal.',
            'submitted_at' => '2026-09-21 10:00:00',
            'stage_updated_at' => '2026-09-21 10:00:00',
            'username' => 'kperera',
            'password' => 'Password@123',
            'user_type' => 'staff',
            'can_login' => '1',
            'role' => 'Super Admin',
        ];

        $sampleRow = [];
        foreach ($headers as $header) {
            $sampleRow[] = $sampleData[$header] ?? '1';
        }

        return [
            'filename' => "{$table}_import_template.csv",
            'headers' => $headers,
            'sample' => $sampleRow,
        ];
    }
}
