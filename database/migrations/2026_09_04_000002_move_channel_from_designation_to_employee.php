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
        // Remove channel_wise_employment_id from designations
        Schema::table('designations', function (Blueprint $table) {
            $table->dropForeign(['channel_wise_employment_id']);
            $table->dropColumn('channel_wise_employment_id');
        });

        // Add channel_wise_employment_id to employees
        Schema::table('employees', function (Blueprint $table) {
            $table->foreignId('channel_wise_employment_id')->nullable()->after('designation_id')->constrained('channel_wise_employments')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove channel_wise_employment_id from employees
        Schema::table('employees', function (Blueprint $table) {
            $table->dropForeign(['channel_wise_employment_id']);
            $table->dropColumn('channel_wise_employment_id');
        });

        // Restore channel_wise_employment_id to designations
        Schema::table('designations', function (Blueprint $table) {
            $table->foreignId('channel_wise_employment_id')->nullable()->after('department_id')->constrained('channel_wise_employments')->nullOnDelete();
        });
    }
};
