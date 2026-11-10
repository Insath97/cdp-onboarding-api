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
            $table->string('candidate_code', 30);
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('nic_number', 50);
            $table->string('phone_number', 30);
            $table->string('secondary_number', 30)->nullable();
            $table->foreignId('designation_id')->constrained('designations')->onDelete('cascade');
            $table->decimal('expected_salary', 12, 2);
            $table->string('cv_path');
            $table->enum('status', [
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
                'rejected',
            ])->default('pending');
            $table->string('document_upload_token', 64)->nullable();
            $table->enum('source', ['direct', 'referral'])->default('referral');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->onDelete('cascade');
            $table->text('uploader_comments')->nullable();
            $table->timestamp('submitted_at');
            $table->foreignId('channel_head_employee_id')->nullable()->constrained('employees')->onDelete('set null');
            $table->timestamp('stage_updated_at')->nullable();
            $table->timestamps();

            // Indexes for fast lookups by NIC number and phone number
            $table->index('nic_number');
            $table->index('phone_number');
            $table->unique('candidate_code');
            $table->unique('document_upload_token');
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
