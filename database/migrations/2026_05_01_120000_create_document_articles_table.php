<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_articles', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('title');
            $table->string('subtitle')->nullable();
            $table->text('description')->nullable();
            $table->string('keywords', 500)->nullable();
            $table->string('author_name')->nullable();
            $table->string('author_byline')->nullable();
            $table->string('cover_image_path', 500)->nullable();
            $table->longText('html_body');
            $table->enum('status', ['draft', 'published', 'archived'])->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->foreignId('imported_by')->constrained('users')->cascadeOnDelete();
            $table->string('original_filename');
            $table->char('original_doc_hash', 64)->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('published_at');
            $table->index('original_doc_hash');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_articles');
    }
};
