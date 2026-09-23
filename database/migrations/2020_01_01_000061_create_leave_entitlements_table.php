<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_entitlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('party_id')->constrained()->cascadeOnDelete();
            $table->date('service_year_start');
            $table->decimal('annual_entitled_days', 5, 1)->default(0)
                ->comment('30 calendar days at 1 year+; 2 days/month between 6 and 12 months');
            $table->decimal('annual_taken_days', 5, 1)->default(0);
            $table->decimal('sick_full_taken', 5, 1)->default(0);
            $table->decimal('sick_half_taken', 5, 1)->default(0);
            $table->decimal('sick_unpaid_taken', 5, 1)->default(0);
            $table->timestamps();

            $table->unique(['party_id', 'service_year_start'], 'le_party_year_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_entitlements');
    }
};
