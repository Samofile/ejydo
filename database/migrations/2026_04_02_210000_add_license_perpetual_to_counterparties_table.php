<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('counterparties', function (Blueprint $table) {
            $table->boolean('license_perpetual')->default(false)->after('license_number');
        });
    }

    public function down(): void
    {
        Schema::table('counterparties', function (Blueprint $table) {
            $table->dropColumn('license_perpetual');
        });
    }
};
