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
        // Check if table already exists
        if (!Schema::hasTable('master_products')) {
            Schema::create('master_products', function (Blueprint $table) {
                $table->string('id')->primary();
                $table->string('name');
                $table->text('description')->nullable();
                $table->text('short_description')->nullable();
                $table->string('categoryID')->nullable();
                $table->string('photo')->nullable();
                $table->json('photos')->nullable();
                $table->decimal('suggested_price', 10, 2)->nullable();
                $table->decimal('min_price', 10, 2)->nullable();
                $table->decimal('dis_price', 10, 2)->default(0);
                $table->boolean('publish')->default(true);
                $table->boolean('nonveg')->default(false);
                $table->boolean('veg')->default(true);
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Only drop if table exists and you want to remove it
         Schema::dropIfExists('master_products');
    }
};

