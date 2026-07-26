<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_listings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('community_id')->constrained('communities')->cascadeOnDelete();
            $table->foreignId('resident_id')->constrained('residents')->cascadeOnDelete();
            $table->string('title');
            $table->text('description');
            $table->enum('type', ['sale', 'rent', 'share', 'request']);
            $table->decimal('price', 12, 2)->nullable();
            $table->enum('status', ['active', 'reserved', 'closed'])->default('active');
            $table->timestamp('expires_at');
            $table->timestamp('closed_at')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['community_id', 'status']);
            $table->index(['type', 'status']);
            $table->index('resident_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_listings');
    }
};
