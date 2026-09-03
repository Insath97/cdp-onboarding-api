<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('f_name');
            $table->string('l_name');
            $table->string('full_name');
            $table->string('name_with_initials');
            $table->string('employee_code')->unique()->nullable();
            $table->foreignId('reporting_manager_id')->nullable()->constrained('employees')->nullOnDelete();

            // Org and Territory connections
            $table->foreignId('province_id')->nullable()->constrained('provinces')->nullOnDelete();
            $table->foreignId('region_id')->nullable()->constrained('regions')->nullOnDelete();
            $table->foreignId('zonal_id')->nullable()->constrained('zonals')->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignId('designation_id')->constrained('designations')->cascadeOnDelete();
            
            $table->enum('employee_type', ['permanent', 'contract', 'internship', 'probation']);
            $table->enum('id_type', ['nic', 'passport', 'driving_license', 'other']);
            $table->string('id_number')->unique();
            $table->date('date_of_birth');
            $table->string('email')->unique();
            $table->string('phone')->nullable();
            $table->string('address_line_1')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('country')->default('Sri Lanka');
            $table->string('postal_code')->nullable();
            $table->string('phone_primary');
            $table->string('phone_secondary')->nullable();
            $table->boolean('have_whatsapp')->default(false);
            $table->string('whatsapp_number')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Resolve circular dependency for Department Head
        Schema::table('departments', function (Blueprint $table) {
            $table->foreign('head_id')->references('id')->on('employees')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove foreign key first to avoid drop constraint error
        Schema::table('departments', function (Blueprint $table) {
            $table->dropForeign(['head_id']);
        });

        Schema::dropIfExists('employees');
    }
};
