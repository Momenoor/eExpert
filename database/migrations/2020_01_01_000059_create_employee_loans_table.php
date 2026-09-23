<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_loans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('party_id')->constrained()->cascadeOnDelete();

            $table->string('kind', 16)->default('loan')->comment('loan, petty_cash');
            $table->decimal('principal', 12, 2)->comment('A in the schedule formula');
            $table->unsignedTinyInteger('months')->comment('N in the schedule formula');
            $table->date('starts_on')->comment('First instalment period');

            $table->string('status', 16)->default('active')
                ->comment('active, settled, written_off');
            $table->text('notes')->nullable();

            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['party_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_loans');
    }
};
