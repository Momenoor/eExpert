<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_log', function (Blueprint $table) {
            $table->id();
            $table->string('log_name')->nullable();
            $table->text('description');
            $table->string('subject_type')->nullable();
            $table->string('event')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('causer_type')->nullable();
            $table->unsignedBigInteger('causer_id')->nullable();
            $table->text('properties')->nullable();
            $table->text('attribute_changes');
            $table->char('batch_uuid', 36)->nullable();
            $table->timestamps();

            // Audit control columns.
            $table->unsignedTinyInteger('risk_score')->nullable();
            $table->string('risk_level', 16)->nullable();
            $table->boolean('retention_hold')->default(false);
            $table->char('integrity_hash', 64)->nullable();
            $table->string('request_id', 100)->nullable();
            $table->string('ip_address', 45)->nullable();

            $table->index(['subject_type', 'subject_id'], 'subject');
            $table->index(['causer_type', 'causer_id'], 'causer');
            $table->index('log_name');
            $table->index('risk_score');
            $table->index('risk_level');
            $table->index('retention_hold');
            $table->index('integrity_hash');
            $table->index(['log_name', 'created_at'], 'activity_log_log_created_index');
            $table->index(['event', 'created_at'], 'activity_log_event_created_index');
            $table->index(['subject_type', 'subject_id', 'created_at'], 'activity_log_subject_created_index');
            $table->index(['causer_type', 'causer_id', 'created_at'], 'activity_log_causer_created_index');
            $table->index(['risk_level', 'created_at'], 'activity_log_risk_created_index');
            $table->index(['request_id', 'created_at'], 'activity_log_request_created_index');
            $table->index(['ip_address', 'created_at'], 'activity_log_ip_created_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_log');
    }
};
