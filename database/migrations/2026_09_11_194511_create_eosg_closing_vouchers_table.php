<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The annual EOSG closing voucher, generated and saved once a year — not
 * recomputed on the fly like the monthly Salaries voucher. A payroll
 * correction made after the year is closed must not silently reshape what was
 * already posted; regenerating is a deliberate action that replaces this
 * saved snapshot, not something that happens by merely opening the page.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('eosg_closing_vouchers')) {
            Schema::create('eosg_closing_vouchers', function (Blueprint $table) {
                $table->id();
                $table->unsignedSmallInteger('year')->unique();
                $table->decimal('total_amount', 12, 2)->default(0);
                $table->timestamp('generated_at');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('eosg_closing_voucher_lines')) {
            Schema::create('eosg_closing_voucher_lines', function (Blueprint $table) {
                $table->id();
                $table->foreignId('eosg_closing_voucher_id')->constrained()->cascadeOnDelete();
                $table->foreignId('party_id')->constrained()->cascadeOnDelete();
                $table->decimal('amount', 12, 2);
                $table->timestamps();

                $table->unique(['eosg_closing_voucher_id', 'party_id'], 'eosg_closing_voucher_lines_voucher_party_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('eosg_closing_voucher_lines');
        Schema::dropIfExists('eosg_closing_vouchers');
    }
};
