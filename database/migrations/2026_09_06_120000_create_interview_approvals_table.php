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
        Schema::create('interview_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('candidate_id')->constrained('candidates')->onDelete('cascade');
            $table->foreignId('actor_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('assigned_to_user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->string('stage', 50); // 1st_interview, 2nd_interview, final_approval
            $table->string('action', 50); // assigned, approved, rejected
            $table->date('interview_date')->nullable();
            $table->date('assigned_date')->nullable();
            $table->text('comments');
            $table->string('status', 50)->default('completed'); // pending, completed, rejected
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('interview_approvals');
    }
};
