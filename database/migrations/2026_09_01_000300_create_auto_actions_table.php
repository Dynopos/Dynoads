<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ANDAIAN FASA 0 — gantikan semasa merge. Lihat nota dalam migration ad_sets.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auto_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ad_set_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type');
            $table->json('payload_json')->nullable();
            $table->timestamp('executed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auto_actions');
    }
};
