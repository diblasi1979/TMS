<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delivery_orders', function (Blueprint $table) {
            $table->timestamp('optimizer_exported_at')->nullable()->after('notes');
            $table->string('optimizer_external_id', 100)->nullable()->after('optimizer_exported_at');
            $table->json('optimizer_last_payload')->nullable()->after('optimizer_external_id');
            $table->json('optimizer_last_response')->nullable()->after('optimizer_external_id');
            $table->text('optimizer_last_error')->nullable()->after('optimizer_last_response');
        });
    }

    public function down(): void
    {
        Schema::table('delivery_orders', function (Blueprint $table) {
            $table->dropColumn([
                'optimizer_exported_at',
                'optimizer_external_id',
                'optimizer_last_payload',
                'optimizer_last_response',
                'optimizer_last_error',
            ]);
        });
    }
};