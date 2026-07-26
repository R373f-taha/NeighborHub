<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('community_id')->constrained('communities')->cascadeOnDelete();
            $table->foreignId('resident_id')->constrained('residents')->cascadeOnDelete();
            $table->enum('category', ['general', 'lost_found', 'question', 'event', 'recommendation']);
            $table->text('content');
            $table->timestamp('is_pinned')->nullable();
            $table->foreignId('pinned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['community_id', 'category']);
            $table->index('resident_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('posts');
    }
};
