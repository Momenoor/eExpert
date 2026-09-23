<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_salary_components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('party_id')->constrained()->cascadeOnDelete();
            $table->string('component', 32)->comment('basic, housing, transport, utilities, other');
            $table->decimal('amount', 12, 2)->comment('Monthly AED');
            $table->date('effective_from');
            $table->date('effective_to')->nullable()->comment('Null = current');
            $table->timestamps();

            $table->index(['party_id', 'component', 'effective_from'], 'esc_party_component_from_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_salary_components');
    }
};
