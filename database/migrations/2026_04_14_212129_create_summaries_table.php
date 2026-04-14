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
        Schema::create('summaries', function (Blueprint $table) {
            $table->id();
            
            $table->string('report_type')->default('barangay');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();

            $table->integer('total_population')->default(0);

            // 👶 CHILDREN (0–19)
            $table->integer('child_total')->default(0);
            $table->integer('child_underweight')->default(0);
            $table->integer('child_stunted')->default(0);
            $table->integer('child_wasted')->default(0);
            $table->integer('child_overweight')->default(0);
            $table->integer('child_obese')->default(0);
            $table->integer('child_healthy')->default(0);
            $table->integer('child_at_risk')->default(0);
            $table->integer('child_moderate')->default(0);
            $table->integer('child_severe')->default(0);

            // 🧑 ADULTS (20+)
            $table->integer('adult_total')->default(0);
            $table->integer('adult_wasted')->default(0);
            $table->integer('adult_overweight')->default(0);
            $table->integer('adult_obese')->default(0);
            $table->integer('adult_healthy')->default(0);
            $table->integer('adult_at_risk')->default(0);
            $table->integer('adult_moderate')->default(0);
            $table->integer('adult_severe')->default(0);

            // DSS OUTPUT
            $table->text('remark')->nullable();
            $table->json('recommendations')->nullable();
            $table->json('chart_data')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('summaries');
    }
};
