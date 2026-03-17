<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! DB::table('status_labels')->where('name', 'Sold')->exists()) {
            DB::table('status_labels')->insert([
                'name' => 'Sold',
                'archived' => 1,
                'deployable' => 0,
                'pending' => 0,
                'default_purchase_label' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('status_labels')
            ->where('name', 'Sold')
            ->where('archived', 1)
            ->delete();
    }
};
