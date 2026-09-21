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
        Schema::create('ad_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ad_set_id')->constrained()->cascadeOnDelete();
            $table->string('image_path')->nullable();
            $table->string('image_hash')->nullable();
            $table->text('caption')->nullable();
            $table->string('headline')->nullable();
            $table->string('meta_campaign_id')->nullable();
            $table->string('meta_adset_id')->nullable();
            $table->string('meta_ad_id')->nullable();
            $table->string('status')->default('draft');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ad_variants');
    }
};
