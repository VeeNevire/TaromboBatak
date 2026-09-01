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
        Schema::table('people', function (Blueprint $table) {
            $table->string('province_code', 10)->nullable()->after('marga_id');
            $table->string('regency_code', 10)->nullable()->after('province_code');
            $table->string('district_code', 15)->nullable()->after('regency_code');
            $table->string('village_code', 20)->nullable()->after('district_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('people', function (Blueprint $table) {
            $table->dropColumn(['province_code', 'regency_code', 'district_code', 'village_code']);
        });
    }
};
