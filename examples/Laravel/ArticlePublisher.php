<?php

declare(strict_types=1);

namespace App\Blost;

use App\Models\BlogPost;
use Corpuscom\Blost\Article;
use Corpuscom\Blost\Contract\ArticlePublisher as PublisherContract;
use Corpuscom\Blost\PublishedArticle;
use Illuminate\Support\Facades\DB;

/**
 * Adapt BlogPost and its fields to your application. Required migration:
 * $table->uuid('blost_id')->nullable()->unique();
 * Add the metadata columns below and allow them in BlogPost::$fillable.
 * Cast published_at to datetime. Provide your public blog.show route.
 */
final class ArticlePublisher implements PublisherContract
{
    public function publish(Article $article): PublishedArticle
    {
        return DB::transaction(function () use ($article): PublishedArticle {
            // Eloquent's updateOrCreate handles a unique conflict on concurrent creates.
            $post = BlogPost::updateOrCreate(['blost_id' => $article->id], [
                'title' => $article->title,
                'content' => $article->content,
                'excerpt' => $article->excerpt,
                'meta_title' => $article->metaTitle,
                'meta_description' => $article->metaDescription,
                'image_url' => $article->imageUrl,
                'language' => $article->language,
            ]);
            // Preserve the first publication date when the sender supplies null.
            $post->published_at = $article->publishedAt ?? $post->published_at ?? now();
            $post->save();

            return new PublishedArticle(route('blog.show', $post));
        });
    }
}
