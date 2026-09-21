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
        Schema::create('metrics_daily', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ad_variant_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->unsignedInteger('spend_sen')->default(0);
            $table->unsignedInteger('impressions')->default(0);
            $table->unsignedInteger('clicks')->default(0);
            $table->unsignedInteger('leads')->default(0);
            $table->unsignedInteger('cost_per_lead_sen')->nullable();
            $table->timestamps();

            $table->unique(['ad_variant_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('metrics_daily');
    }
};
