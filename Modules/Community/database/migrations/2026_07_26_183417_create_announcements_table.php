<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('community_id')->constrained('communities')->cascadeOnDelete();
            $table->foreignId('created_by_manager')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->text('content');
            $table->enum('priority', ['normal', 'important', 'urgent'])->default('normal');
            $table->timestamp('pinned_until')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['community_id', 'priority']);
            $table->index('created_by_manager');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcements');
    }
};
