<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('fees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('matter_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            $table->string('type')->nullable();
            $table->decimal('amount', 15, 2)->default(0);
            $table->date('date')->nullable();
            $table->text('description')->nullable();
            $table->string('status')->default('unpaid');
            $table->timestamps();
        });

        Schema::create('allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fee_id')->constrained()->onDelete('cascade');
            $table->foreignId('matter_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            $table->decimal('amount', 15, 2)->default(0);
            $table->date('date')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        if (Schema::hasTable('matters')) {
            Schema::table('matters', function (Blueprint $table) {
                if (! Schema::hasColumn('matters', 'collection_status')) {
                    $table->string('collection_status')->default('no_fees');
                }
            });
        }
    }

    public function down()
    {
        if (Schema::hasTable('matters')) {
            Schema::table('matters', function (Blueprint $table) {
                $table->dropColumn('collection_status');
            });
        }
        Schema::dropIfExists('allocations');
        Schema::dropIfExists('fees');
    }
};
