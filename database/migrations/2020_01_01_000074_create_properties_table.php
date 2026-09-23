<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('properties', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('emirate')->nullable();
            $table->string('address')->nullable();
            $table->string('municipality')->nullable();
            $table->string('suburb')->nullable();
            $table->string('area')->nullable();
            $table->string('title_deed_number')->nullable();
            $table->date('title_deed_date')->nullable();
            $table->string('plot_number')->nullable();
            $table->string('property_type')->nullable();
            $table->string('property_number')->nullable();
            $table->foreignId('owner_group_id')->nullable()->constrained('owner_groups')->nullOnDelete();
            $table->foreignId('owner_group_bank_account_id')->nullable()
                ->constrained('owner_group_bank_accounts')->nullOnDelete();
            $table->unsignedInteger('total_units')->nullable();
            $table->unsignedSmallInteger('year_built')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('properties');
    }
};
