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
        Schema::create('patients', function (Blueprint $table) {
            $table->id();
            $table->string("name");
            $table->string("address");
            $table->string("belongs_to_ip");
            $table->string("sex");
            $table->date("birthday");
            $table->string("contact_number");
            
            // $table->date("date_measured");
            // $table->integer("weight");
            // $table->integer("height");
            // $table->integer("age");
            // $table->string("weight_for_age"); //underweight //normal //overweight
            // $table->string("height_for_age"); //stunted //normal //tall
            // $table->string("weight_for_ltht_status"); //wasted //normal //obese

            // $table->text("immunizations")->nullable();
            // $table->date("last_deworming_date")->nullable();
            // $table->string("allergies")->nullable();
            // $table->text("medical_history")->nullable();
            // $table->text("notes")->nullable();
            
            // $table->string("status")->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('patients');
    }
};
