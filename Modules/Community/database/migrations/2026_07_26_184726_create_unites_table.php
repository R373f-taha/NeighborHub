<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('community_id')->constrained('communities')->onDelete('cascade');
            $table->string('unit_number', 80);
            $table->string('building_name', 120)->nullable();
            $table->integer('rooms')->default(0);
            $table->enum('unit_type', ['apartment', 'villa'])->default('apartment');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['community_id', 'unit_number'], 'unique_unit_per_community');
            $table->index(['community_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('units');
    }
};
