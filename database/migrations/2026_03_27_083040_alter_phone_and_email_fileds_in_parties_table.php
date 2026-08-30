<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('parties')) {
            DB::table('parties')->get()->each(function ($party) {
                DB::table('parties')
                    ->where('id', $party->id)
                    ->update([
                        // Wrapping the plain string into a JSON array: "value" -> ["value"]
                        'email' => json_encode(array_filter([$party->email])),
                        'phone' => json_encode(array_filter([$party->phone])),
                    ]);
            });
            Schema::table('parties', function (Blueprint $table) {
                $table->json('email')->nullable()->change();
                $table->json('phone')->nullable()->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('parties')) {
            DB::table('parties')->get()->each(function ($party) {
                $emails = json_decode($party->email, true);
                $phones = json_decode($party->phone, true);

                DB::table('parties')
                    ->where('id', $party->id)
                    ->update([
                        // Take the first item out of the array back to a string
                        'email' => is_array($emails) ? ($emails[0] ?? null) : $party->email,
                        'phone' => is_array($phones) ? ($phones[0] ?? null) : $party->phone,
                    ]);
            });
            Schema::table('parties', function (Blueprint $table) {
                $table->string('email')->nullable()->change();
                $table->string('phone')->nullable()->change();
            });
        }
    }
};
