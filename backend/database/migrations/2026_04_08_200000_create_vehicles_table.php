<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->string('plate', 20)->unique();
            $table->string('type', 30);
            $table->string('brand', 100);
            $table->string('model', 100);
            $table->smallInteger('year');
            $table->string('color', 50)->nullable();
            $table->integer('payload_kg')->nullable();
            $table->decimal('volume_m3', 8, 2)->nullable();
            $table->string('fuel_type', 20)->default('diesel');
            $table->string('status', 20)->default('available');
            $table->integer('current_mileage')->default(0);
            $table->date('insurance_expiry')->nullable();
            $table->date('technical_review_expiry')->nullable();
            $table->date('circulation_permit_expiry')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};
