<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql' || $driver === 'mariadb') {
            DB::statement("ALTER TABLE candidates MODIFY COLUMN status ENUM(
                'pending',
                'document_submitted',
                'document_verified',
                'document_rejected',
                'document_fully_rejected',
                '1st_interview',
                '1st_interview_passed',
                '1st_interview_rejected',
                '2nd_interview',
                '2nd_interview_passed',
                '2nd_interview_rejected',
                'final_approved',
                'rejected'
            ) NOT NULL DEFAULT 'pending'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql' || $driver === 'mariadb') {
            DB::statement("ALTER TABLE candidates MODIFY COLUMN status ENUM(
                'pending',
                'document_submitted',
                'document_verified',
                'document_rejected',
                '1st_interview',
                '1st_interview_passed',
                '1st_interview_rejected',
                '2nd_interview',
                '2nd_interview_passed',
                '2nd_interview_rejected',
                'final_approved',
                'rejected'
            ) NOT NULL DEFAULT 'pending'");
        }
    }
};
