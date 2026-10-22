<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('blost_posts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->text('title');
            $table->longText('content');
            $table->text('excerpt')->nullable();
            $table->text('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->text('image_url')->nullable();
            $table->text('language')->nullable();
            $table->dateTime('published_at')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blost_posts');
    }
};
