<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('issue_status_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('issue_id')->unique()->constrained('issues')->cascadeOnDelete();
            $table->enum('old_status', ['open', 'assigned', 'in_progress', 'resolved', 'closed'])->nullable();
            $table->enum('new_status', ['open', 'assigned', 'in_progress', 'resolved', 'closed']);
            $table->foreignId('changed_by')->constrained('users')->cascadeOnDelete();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['issue_id', 'created_at']);
            $table->index('changed_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('issue_status_log');
    }
};
