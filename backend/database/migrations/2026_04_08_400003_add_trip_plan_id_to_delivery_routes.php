<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delivery_routes', function (Blueprint $table) {
            $table->foreignId('trip_plan_id')->nullable()->after('assignment_id')
                  ->constrained('trip_plans')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('delivery_routes', function (Blueprint $table) {
            $table->dropForeignIdFor(\App\Models\TripPlan::class, 'trip_plan_id');
            $table->dropColumn('trip_plan_id');
        });
    }
};
