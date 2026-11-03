<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * client_id null = akaun DynoPOS sendiri (nilai dari .env), seperti sebelum ini.
 * Jadi semua ad set lama terus berfungsi tanpa diubah.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ad_sets', function (Blueprint $table) {
            // restrict: pelanggan yang ada sejarah iklan tidak boleh dipadam
            // senyap-senyap — rekod tu diperlukan untuk report dan pertikaian.
            $table->foreignId('client_id')->nullable()->after('user_id')
                ->constrained('clients')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ad_sets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('client_id');
        });
    }
};
