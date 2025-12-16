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
        Schema::create('patient_records', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger("patient_id");
            $table->date("date_measured");
            $table->decimal("weight", 10, 2);
            $table->decimal("height", 10, 2);
            $table->integer("age");
            $table->string("weight_for_age"); //underweight //normal //overweight
            $table->string("height_for_age"); //stunted //normal //tall
            $table->string("weight_for_ltht_status"); //wasted //normal //obese

            $table->text("immunizations")->nullable();
            $table->date("last_deworming_date")->nullable();
            $table->string("allergies")->nullable();
            $table->text("medical_history")->nullable();
            $table->text("notes")->nullable();
            
            $table->string("status")->nullable();

            $table->text('likely_cause')->nullable();
            $table->json('questionnaire_data')->nullable();

            $table->foreign("patient_id")->references("id")->on("patients")->onDelete("cascade");
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('patient_records');
    }
};
