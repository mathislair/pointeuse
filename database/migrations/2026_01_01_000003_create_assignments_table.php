<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row = one worker planned on one shift in one restaurant, plus the
        // clocking data (arrival, departure, signature) attached to it.
        Schema::create('assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('worker_id')->constrained()->cascadeOnDelete();
            $table->date('date');                 // service day
            $table->string('shift', 10);          // open | swing | close – deduced from the planned range
            $table->dateTime('planned_start');
            $table->dateTime('planned_end')->nullable(); // null = open-ended (tablet walk-in): the departure is the end
            $table->dateTime('clock_in_at')->nullable();
            $table->dateTime('clock_out_at')->nullable();
            $table->text('signature')->nullable(); // PNG data URL drawn by the worker
            $table->dateTime('signed_at')->nullable();
            $table->boolean('absent')->default(false);
            $table->text('admin_note')->nullable();
            $table->dateTime('edited_at')->nullable(); // last manual correction by the admin
            $table->timestamps();

            $table->index(['date', 'restaurant_id']);
            $table->index(['worker_id', 'planned_start']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assignments');
    }
};
