<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('notification_id')->unique()->constrained('notifications')->cascadeOnDelete();
            $table->enum('channel', ['email', 'push', 'sms', 'database']);
            $table->enum('status', ['pending', 'sent', 'failed', 'delivered'])->default('pending');
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['notification_id', 'status']);
            $table->index('channel');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_log');
    }
};
