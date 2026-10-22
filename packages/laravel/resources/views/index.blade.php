@extends('blost-blog::layout')
@section('content')
    <h1>{{ config('blost-blog.title', 'Blog') }}</h1>
    @forelse ($posts as $post)
        <article>
            <h2><a href="{{ route('blost.blog.show', ['id' => $post->id]) }}">{{ $post->title }}</a></h2>
            @if ($post->excerpt)<p>{{ $post->excerpt }}</p>@endif
        </article>
    @empty
        <p>No articles yet.</p>
    @endforelse
    <nav aria-label="Pagination">
        @if ($posts->previousPageUrl())<a href="{{ $posts->previousPageUrl() }}">Previous</a>@endif
        @if ($posts->nextPageUrl())<a href="{{ $posts->nextPageUrl() }}">Next</a>@endif
    </nav>
@endsection
