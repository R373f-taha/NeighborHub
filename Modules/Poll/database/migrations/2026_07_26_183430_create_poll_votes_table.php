<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('poll_votes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('poll_id')->constrained('polls')->cascadeOnDelete();
            $table->foreignId('option_id')->constrained('poll_options')->cascadeOnDelete();
            $table->timestamp('submitted_at')->useCurrent();
            $table->foreignId('voter_id')->constrained('residents')->cascadeOnDelete();
            $table->date('voted_at');
            $table->unique(['poll_id', 'voter_id']);
            $table->index('option_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('poll_votes');
    }
};
