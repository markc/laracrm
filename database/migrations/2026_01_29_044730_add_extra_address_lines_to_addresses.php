<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('addresses', function (Blueprint $table) {
            $table->string('street3')->nullable()->after('street2');
            $table->string('street4')->nullable()->after('street3');
            $table->string('street5')->nullable()->after('street4');
        });
    }

    public function down(): void
    {
        Schema::table('addresses', function (Blueprint $table) {
            $table->dropColumn(['street3', 'street4', 'street5']);
        });
    }
};
