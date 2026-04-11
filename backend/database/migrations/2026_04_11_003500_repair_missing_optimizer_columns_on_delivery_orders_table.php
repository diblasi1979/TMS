<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('delivery_orders')) {
            return;
        }

        Schema::table('delivery_orders', function (Blueprint $table) {
            if (!Schema::hasColumn('delivery_orders', 'optimizer_exported_at')) {
                $table->timestamp('optimizer_exported_at')->nullable()->after('notes');
            }

            if (!Schema::hasColumn('delivery_orders', 'optimizer_external_id')) {
                $table->string('optimizer_external_id', 100)->nullable()->after('optimizer_exported_at');
            }

            if (!Schema::hasColumn('delivery_orders', 'optimizer_last_payload')) {
                $table->json('optimizer_last_payload')->nullable()->after('optimizer_external_id');
            }

            if (!Schema::hasColumn('delivery_orders', 'optimizer_last_response')) {
                $table->json('optimizer_last_response')->nullable()->after('optimizer_last_payload');
            }

            if (!Schema::hasColumn('delivery_orders', 'optimizer_last_error')) {
                $table->text('optimizer_last_error')->nullable()->after('optimizer_last_response');
            }
        });
    }

    public function down(): void
    {
        // Reparacion irreversible: no se eliminan columnas para evitar perder datos.
    }
};