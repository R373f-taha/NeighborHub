<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('community_id')->constrained('communities')->cascadeOnDelete();
            $table->enum('type', ['direct', 'group', 'appeal'])->default('direct');
            $table->enum('status', ['active', 'archived', 'closed'])->default('active');
            $table->timestamps();

            $table->index(['community_id', 'status']);
          
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};
