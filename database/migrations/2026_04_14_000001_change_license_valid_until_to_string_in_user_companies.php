<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Convert existing date values to string format before changing column type
        DB::statement("ALTER TABLE user_companies MODIFY COLUMN license_valid_until VARCHAR(50) NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Note: converting back may lose 'бессрочная' string values
        DB::statement("ALTER TABLE user_companies MODIFY COLUMN license_valid_until DATE NULL");
    }
};
