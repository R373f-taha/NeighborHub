<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comments', function (Blueprint $table) {
            $table->id();

            $table->morphs('commentable'); // commentable_type + commentable_id

            $table->foreignId('author_id')
                  ->constrained('users')
                  ->cascadeOnDelete();

            $table->foreignId('parent_id')
                  ->nullable()
                  ->constrained('comments')
                  ->cascadeOnDelete();

            $table->text('content');


            $table->softDeletes();

            $table->timestamps();

            $table->index('author_id');
            $table->index('parent_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comments');
    }
};
