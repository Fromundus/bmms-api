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
        Schema::table('summaries', function (Blueprint $table) {
            /*
            |--------------------------------------------------------------------------
            | 🤰 PREGNANT WOMEN
            |--------------------------------------------------------------------------
            */
            $table->unsignedInteger('pregnant_total')->default(0);
            $table->unsignedInteger('pregnant_underweight')->default(0);
            $table->unsignedInteger('pregnant_overweight')->default(0);
            $table->unsignedInteger('pregnant_obese')->default(0);
            $table->unsignedInteger('pregnant_healthy')->default(0);
            $table->unsignedInteger('pregnant_at_risk')->default(0);
            $table->unsignedInteger('pregnant_moderate')->default(0);
            $table->unsignedInteger('pregnant_severe')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('summaries', function (Blueprint $table) {
            $table->dropColumn([
                'pregnant_total',
                'pregnant_underweight',
                'pregnant_overweight',
                'pregnant_obese',
                'pregnant_healthy',
                'pregnant_at_risk',
                'pregnant_moderate',
                'pregnant_severe',
            ]);
        });
    }
};
