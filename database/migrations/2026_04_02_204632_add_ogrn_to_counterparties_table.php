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
        Schema::table('counterparties', function (Blueprint $table) {
            $table->string('ogrn', 15)->nullable()->after('kpp');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('counterparties', function (Blueprint $table) {
            $table->dropColumn('ogrn');
        });
    }
};
