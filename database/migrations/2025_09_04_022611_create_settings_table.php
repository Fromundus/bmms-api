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
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->integer("wfa_underweight");
            $table->integer("wfa_normal");
            $table->integer("wfa_overweight");
            $table->integer("hfa_stunted");
            $table->integer("hfa_normal");
            $table->integer("hfa_tall");
            $table->integer("wfs_wasted");
            $table->integer("wfs_normal");
            $table->integer("wfs_obese");
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
