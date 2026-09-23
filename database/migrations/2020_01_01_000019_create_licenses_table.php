<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('licenses', function (Blueprint $table) {
            $table->id();
            $table->text('key');
            $table->string('fingerprint')->unique()->comment("This installation's own identity, sent on every activate/verify call.");
            $table->string('status')->default('active')->comment('active, suspended, revoked, unknown — mirrors the server\'s own status, "unknown" before the first successful check-in.');
            $table->string('plan')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('last_checked_at')->nullable()->comment('Every check-in attempt, success or failure.');
            $table->timestamp('last_valid_at')->nullable()->comment('Only successful checks — what the grace period counts from.');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('licenses');
    }
};
