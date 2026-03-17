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
        Schema::table('settings', function (Blueprint $table) {
            $table->integer('purchase_age_threshold_months')->default(48);
        });

        Schema::table('status_labels', function (Blueprint $table) {
            $table->boolean('default_purchase_label')->nullable()->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn('purchase_age_threshold_months');
        });

        Schema::table('status_labels', function (Blueprint $table) {
            $table->dropColumn('default_purchase_label');
        });
    }
};
