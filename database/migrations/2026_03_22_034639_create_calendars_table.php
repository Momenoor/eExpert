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
        Schema::create('calendars', function (Blueprint $table) {
            $table->id();
            $table->text('outlook_event_id')->nullable()
                ->comment('Microsoft Graph event ID for the shared Outlook calendar');
            $table->foreignId('matter_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('title')->nullable()->comment('Title of the event');
            $table->dateTime('start_at')->nullable()->comment('Start time of the event');
            $table->dateTime('end_at')->nullable()->comment('End time of the event');
            $table->string('location')->nullable()->comment('Location of the event');
            $table->text('description')->nullable()->comment('Description of the event');
            $table->boolean('is_all_day')->default(false)->comment('Whether the event is all-day');
            $table->string('teams_meeting_url')->nullable()->comment('Teams meeting URL');
            $table->enum('event_type', ['single', 'group'])
                ->default('single')
                ->comment('single = one matter | group = multiple matters same type+date');

            $table->timestamps();
        });

        Schema::create('calendar_matter', function (Blueprint $table) {
            $table->id();
            $table->foreignId('calendar_id')->constrained()->cascadeOnDelete();
            $table->foreignId('matter_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['calendar_id', 'matter_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('calendars');
        Schema::dropIfExists('calendar_matter');
    }
};
