<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('eosg_accruals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('party_id')->constrained()->cascadeOnDelete();
            $table->char('period', 7);

            $table->unsignedInteger('service_days')->default(0);
            $table->decimal('basic_snapshot', 12, 2)->default(0);
            $table->decimal('accrued_this_month', 12, 2)->default(0);
            $table->decimal('cumulative_liability', 12, 2)->default(0)
                ->comment('What settlement would cost today, after the two-year cap');

            $table->timestamps();

            $table->unique(['party_id', 'period'], 'eosg_party_period_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('eosg_accruals');
    }
};
