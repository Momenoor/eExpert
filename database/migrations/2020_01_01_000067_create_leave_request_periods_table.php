<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_request_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('leave_request_id')->constrained('leave_requests')->cascadeOnDelete();
            $table->string('leave_type', 24)
                ->comment('annual, casual, sick_full, sick_half, sick_unpaid, unpaid, absent');
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('day_count', 4, 1)->comment('Halves allow half-day leave');
            $table->decimal('pay_factor', 3, 2)->default(1);
            $table->timestamps();

            $table->index(['leave_request_id', 'leave_type'], 'lrp_request_type_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_request_periods');
    }
};
