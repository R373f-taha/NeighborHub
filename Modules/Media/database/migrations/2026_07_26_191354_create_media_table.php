<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media', function (Blueprint $table) {
            $table->id();

            $table->morphs('mediable'); // mediable_type + mediable_id

            $table->foreignId('uploaded_by')
                  ->constrained('users')
                  ->cascadeOnDelete();

            $table->string('file_path');
            $table->string('file_name');
            $table->string('mime_type');
            $table->unsignedBigInteger('file_size');
            $table->string('disk')->default('public');

            $table->timestamps();

            $table->index('uploaded_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media');
    }
};
