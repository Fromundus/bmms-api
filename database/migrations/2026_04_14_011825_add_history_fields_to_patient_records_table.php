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
        Schema::table('patient_records', function (Blueprint $table) {
            $table->text("birth_history")->nullable();
            $table->text("past_illnesses")->nullable();
            $table->text("current_medication")->nullable();
            $table->text("family_medical_history")->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('patient_records', function (Blueprint $table) {
            $table->dropColumn([
                "birth_history",
                "past_illnesses",
                "current_medication",
                "family_medical_history",
            ]);
        });
    }
};
