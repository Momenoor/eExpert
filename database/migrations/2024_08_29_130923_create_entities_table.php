<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('expert_fields', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('entities', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone')->nullable();
            $table->string('fax')->nullable();
            $table->text('address')->nullable();
            $table->string('email')->nullable();


            // Entity type and relationships
            $table->foreignId('parent_id')->nullable()->constrained('entities')->comment('Parent entity if applicable');
            $table->foreignId('user_id')->nullable()->constrained()->comment('Assigned user');

            $table->timestamps();
            $table->softDeletes();
        });

        // Create entity types table
        Schema::create('entity_roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entity_id')->constrained();
            $table->string('name')->comment('Type of the entity (party, expert, broker, marketer, etc.)');
            $table->text('description')->nullable();
            // Expert-specific fields
            $table->foreignId('expert_field_id')->nullable()->constrained()->comment('Field of expertise (e.g., accounting, engineering)');

            // Party-specific fields
            $table->string('party_type')->nullable()->comment('Type of the party (e.g., office, individual)');
            $table->boolean('is_blacklisted')->default(false)->comment('Is the party blacklisted?');

            // Broker-specific fields
            $table->decimal('commission_rate', 5, 2)->nullable()->comment('Commission percentage for brokers');
            $table->string('bank_name', 191)->nullable()->collation('utf8mb4_unicode_ci');
            $table->string('bank_account_name', 191)->nullable()->collation('utf8mb4_unicode_ci');
            $table->string('bank_account_number', 191)->nullable()->collation('utf8mb4_unicode_ci');
            $table->timestamps();
        });

        // Create entity types table
        Schema::create('entity_sub_roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entity_role_id')->constrained();
            $table->string('name')->comment('Type of the entity (plaintiff, main expert, external broker, etc.)');
            $table->text('description')->nullable();
            $table->timestamps();
        });


        Schema::create('matter_entity', function (Blueprint $table) {
            $table->id();
            $table->foreignId('matter_id')->constrained();
            $table->foreignId('entity_id')->constrained();
            $table->foreignId('entity_sub_role_id')->comment('Role of the entity in this matter (e.g., main_expert, internal_expert, external_expert, committee_member)');
            $table->foreignId('parent_id')->nullable()->constrained('matter_entity');
            // New fields for tracking percentages and amounts
            $table->decimal('work_percentage', 5, 2)->nullable()->comment('Percentage of work done by the expert');
            $table->decimal('broker_percentage', 5, 2)->nullable()->comment('Percentage of the commission for the broker');
            $table->decimal('broker_amount', 10, 2)->nullable()->comment('Fixed amount for the broker');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {

        Schema::dropIfExists('matter_entity');
        Schema::dropIfExists('entity_sub_roles');
        Schema::dropIfExists('entity_roles');
        Schema::dropIfExists('entities');
        Schema::dropIfExists('expert_fields');
    }
};
