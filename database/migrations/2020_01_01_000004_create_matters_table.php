<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('matters', function (Blueprint $table) {
            $table->id();
            $table->year('year');
            $table->string('number', 191);
            $table->string('commissioning', 191)->nullable();
            $table->date('distributed_at')->nullable();
            $table->dateTime('next_session_date')->nullable();
            $table->date('received_at')->nullable()->comment('تاريخ استلام القضية من المحكمة');
            $table->date('initial_report_at')->nullable();
            $table->date('final_report_at')->nullable();
            // Not a foreign key: never enforced by any migration.
            $table->unsignedBigInteger('court_id')->nullable();
            $table->string('level', 255)->nullable();
            $table->unsignedBigInteger('type_id')->nullable();
            $table->json('custom_fields')->nullable();
            $table->foreignId('parent_id')->nullable()->constrained('matters')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->string('difficulty', 191)->nullable()->comment('simple,medium,exceptional');
            $table->string('collection_status')->default('no_fees');
            $table->unsignedTinyInteger('review_count')->default(0);
            $table->boolean('has_substantive_changes')->default(false);
            $table->boolean('has_court_penalty')->default(false);
            $table->date('final_report_memo_date')->nullable();
            $table->boolean('is_office_work')->default(false);

            $table->index('court_id');
            $table->index('type_id');
            $table->index('final_report_at');
            $table->index('initial_report_at');
            $table->index('distributed_at');
            $table->index('next_session_date');
            $table->index('collection_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('matters');
    }
};
