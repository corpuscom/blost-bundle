@extends('blost-blog::layout')
@section('title', $post->meta_title ?? $post->title)
@section('metadata')
    <link rel="canonical" href="{{ route('blost.blog.show', ['id' => $post->id]) }}">
    @if ($post->meta_description !== null)<meta name="description" content="{{ $post->meta_description }}">@endif
    @if ($post->image_url !== null)<meta property="og:image" content="{{ $post->image_url }}">@endif
@endsection
@section('content')
    <article>
        <h1>{{ $post->title }}</h1>
        <time datetime="{{ $post->published_at->toIso8601String() }}">{{ $post->published_at->format('Y-m-d') }}</time>
        @if ($post->image_url !== null)<img src="{{ $post->image_url }}" alt="">@endif
        <div>{!! $post->content !!}</div>
    </article>
@endsection
