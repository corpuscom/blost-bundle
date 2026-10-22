<?php

declare(strict_types=1);

namespace Corpuscom\Blost\LaravelBlog;

use Illuminate\Database\Eloquent\Model;

class Post extends Model
{
    protected $table = 'blost_posts';
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;
    protected $guarded = [];

    protected function casts(): array
    {
        return ['published_at' => UtcDateTimeCast::class];
    }
}
