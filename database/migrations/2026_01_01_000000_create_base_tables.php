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
        if (! Schema::hasTable('courts')) {
            Schema::create('courts', function (Blueprint $table) {
                $table->id();
                $table->string('name')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('types')) {
            Schema::create('types', function (Blueprint $table) {
                $table->id();
                $table->string('name')->nullable();
                $table->boolean('active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('parties')) {
            Schema::create('parties', function (Blueprint $table) {
                $table->id();
                $table->string('name')->nullable();
                $table->string('type')->nullable();
                $table->json('email')->nullable();
                $table->json('phone')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('matters')) {
            Schema::create('matters', function (Blueprint $table) {
                $table->id();
                $table->integer('year')->nullable();
                $table->string('number')->nullable();
                $table->string('commissioning')->nullable();
                $table->dateTime('next_session_date')->nullable();
                $table->dateTime('initial_report_at')->nullable();
                $table->dateTime('final_report_at')->nullable();
                $table->unsignedBigInteger('court_id')->nullable();
                $table->unsignedBigInteger('type_id')->nullable();
                $table->string('level')->nullable();
                $table->string('difficulty')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! Schema::hasTable('matter_party')) {
            Schema::create('matter_party', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('matter_id')->nullable();
                $table->unsignedBigInteger('party_id')->nullable();
                $table->string('type')->nullable();
                $table->unsignedBigInteger('parent_id')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('matter_requests')) {
            Schema::create('matter_requests', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('matter_id')->nullable();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('type')->nullable();
                $table->string('status')->default('pending');
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('attachments')) {
            Schema::create('attachments', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('matter_id')->nullable();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->unsignedBigInteger('matter_request_id')->nullable();
                $table->string('name')->nullable();
                $table->string('type')->nullable();
                $table->string('path')->nullable();
                $table->string('size')->nullable();
                $table->string('extension')->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attachments');
        Schema::dropIfExists('matter_requests');
        Schema::dropIfExists('matter_party');
        Schema::dropIfExists('matters');
        Schema::dropIfExists('parties');
        Schema::dropIfExists('types');
        Schema::dropIfExists('courts');
    }
};
