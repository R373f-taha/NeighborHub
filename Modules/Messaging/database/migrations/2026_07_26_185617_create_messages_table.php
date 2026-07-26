<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained('conversations')->cascadeOnDelete();
            $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
            $table->text('content');
            $table->boolean('is_read')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->foreignId('community_id')->constrained('communities')->cascadeOnDelete();
            $table->index(['conversation_id', 'created_at']);
            $table->index(['sender_id', 'is_read']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
