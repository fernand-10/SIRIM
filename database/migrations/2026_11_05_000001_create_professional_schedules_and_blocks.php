<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('professional_schedules', function (Blueprint $table) {
            $table->id();

            $table->foreignId('professional_id')
                ->constrained()
                ->cascadeOnDelete();

            // Lunes = 1 ... Domingo = 7
            $table->unsignedTinyInteger('day_of_week');

            $table->time('start_time');
            $table->time('end_time');

            $table->unsignedSmallInteger('slot_duration')->default(20);

            $table->boolean('is_active')->default(true);

            $table->timestamps();

            // Nombre corto para evitar el límite de MySQL
            $table->index(
                ['professional_id', 'day_of_week', 'is_active'],
                'prof_sched_prof_day_active_idx'
            );
        });

        Schema::create('professional_blocks', function (Blueprint $table) {
            $table->id();

            $table->foreignId('professional_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->dateTime('starts_at');
            $table->dateTime('ends_at');

            $table->string('reason', 255);

            $table->foreignId('created_by')
                ->constrained('users')
                ->restrictOnDelete();

            $table->timestamps();

            $table->index(
                ['professional_id', 'starts_at', 'ends_at'],
                'prof_blocks_prof_time_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('professional_blocks');

        Schema::dropIfExists('professional_schedules');
    }
};