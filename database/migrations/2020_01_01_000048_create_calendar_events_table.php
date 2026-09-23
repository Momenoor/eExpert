<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calendar_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('matter_id')->nullable()->constrained('matters')->nullOnDelete();
            $table->string('outlook_event_id')->nullable();
            $table->string('title');
            $table->boolean('is_all_day')->default(false);
            $table->text('description')->nullable();
            $table->dateTime('start_datetime');
            $table->dateTime('end_datetime')->nullable();
            $table->string('location')->nullable();
            $table->enum('type', ['single', 'bulk'])->default('single');
            $table->boolean('update_next_session_date')->default(true);
            $table->boolean('synced_to_outlook')->default(false);
            $table->boolean('imported_from_outlook')->default(false);
            $table->boolean('is_teams_meeting')->default(false);
            $table->text('online_meeting_url')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calendar_events');
    }
};
