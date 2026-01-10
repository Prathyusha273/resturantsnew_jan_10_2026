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
        // Column already exists in the database, so this migration does nothing
        // The bill_status column is already present in the vendors table
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Column already exists, so we don't drop it
    }
};
