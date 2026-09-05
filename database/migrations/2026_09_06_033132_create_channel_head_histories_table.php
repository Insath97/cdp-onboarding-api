<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Stores the history of Channel Head assignments per channel.
     * Only ONE row per channel should have effective_to = NULL (the current active head).
     * All employees under the Channel Head via reporting_manager_id chain
     * are implicitly in that channel — no need to set channel on individual employees.
     */
    public function up(): void
    {
        Schema::create('channel_head_histories', function (Blueprint $table) {
            $table->id();

            $table->foreignId('channel_wise_employment_id')
                  ->constrained('channel_wise_employments')
                  ->cascadeOnDelete();

            // The employee who IS the channel head during this period
            $table->foreignId('employee_id')
                  ->constrained('employees')
                  ->cascadeOnDelete();

            $table->date('effective_from');
            $table->date('effective_to')->nullable(); // NULL = currently active

            $table->text('remarks')->nullable();

            // Admin who made this assignment change
            $table->foreignId('changed_by')
                  ->constrained('users')
                  ->cascadeOnDelete();

            $table->timestamps();

            // Fast point-in-time lookup index
            $table->index(
                ['channel_wise_employment_id', 'effective_from', 'effective_to'],
                'idx_channel_head_lookup'
            );

            // Fast lookup: who is the current head of channel X?
            $table->index(
                ['employee_id', 'effective_to'],
                'idx_employee_head_status'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('channel_head_histories');
    }
};

