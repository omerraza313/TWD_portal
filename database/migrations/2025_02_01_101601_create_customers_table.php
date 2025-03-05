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
            Schema::create('customers', function (Blueprint $table) {
                $table->id();
                $table->bigInteger('live_id'); // WordPress customer ID
                $table->string('email')->nullable();
                $table->string('first_name')->nullable();
                $table->string('last_name')->nullable();
                $table->string('role')->default('customer');
                $table->string('username')->nullable();
                $table->boolean('is_paying_customer')->default(false);
                $table->string('avatar_url')->nullable();
                $table->boolean('is_processed')->default(false);
                $table->boolean('process_status')->default(false);
                $table->timestamps();
            });
        }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
