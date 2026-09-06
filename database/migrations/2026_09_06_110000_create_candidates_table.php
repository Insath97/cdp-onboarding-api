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
        Schema::create('candidates', function (Blueprint $table) {
            $table->id();
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('nic_number', 50);
            $table->string('phone_number', 30);
            $table->string('secondary_number', 30)->nullable();
            $table->foreignId('designation_id')->constrained('designations')->onDelete('cascade');
            $table->decimal('expected_salary', 12, 2);
            $table->string('cv_path');
            $table->string('status', 50)->default('pending'); // pending, 1st_interview, 1st_interview_passed, etc.
            $table->foreignId('uploaded_by')->constrained('users')->onDelete('cascade');
            $table->text('uploader_comments')->nullable();
            $table->timestamp('submitted_at');
            $table->foreignId('channel_head_employee_id')->nullable()->constrained('employees')->onDelete('set null');
            $table->timestamp('stage_updated_at')->nullable();
            $table->timestamps();

            // Composite unique constraint (designation_id, phone_number, nic_number)
            $table->unique(['designation_id', 'phone_number', 'nic_number'], 'candidates_desig_phone_nic_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('candidates');
    }
};
