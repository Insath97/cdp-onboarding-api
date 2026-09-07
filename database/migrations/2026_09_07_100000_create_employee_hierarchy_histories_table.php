<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Stores point-in-time reporting manager assignments for employees.
     * Only ONE active record (effective_to IS NULL) per employee at any given time.
     */
    public function up(): void
    {
        Schema::create('employee_hierarchy_histories', function (Blueprint $table) {
            $table->id();

            // The subordinate employee whose manager assignment is tracked
            $table->foreignId('employee_id')
                  ->constrained('employees')
                  ->cascadeOnDelete();

            // The reporting manager during this period (NULL if top-level / no manager)
            $table->foreignId('reporting_manager_id')
                  ->nullable()
                  ->constrained('employees')
                  ->nullOnDelete();

            $table->date('effective_from');
            $table->date('effective_to')->nullable(); // NULL = currently active

            $table->string('reason', 500)->nullable();

            // Admin/user who changed the manager assignment
            $table->foreignId('changed_by')
                  ->constrained('users')
                  ->cascadeOnDelete();

            $table->timestamps();

            // Index for point-in-time employee lookup
            $table->index(
                ['employee_id', 'effective_from', 'effective_to'],
                'idx_emp_hierarchy_lookup'
            );

            // Index for finding all subordinates under a manager in a date range
            $table->index(
                ['reporting_manager_id', 'effective_from', 'effective_to'],
                'idx_mgr_subordinates_lookup'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_hierarchy_histories');
    }
};
