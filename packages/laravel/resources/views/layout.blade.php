<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', config('blost-blog.title', 'Blog'))</title>
    @yield('metadata')
</head>
<body>
    <header><a href="{{ route('blost.blog.index') }}">{{ config('blost-blog.title', 'Blog') }}</a></header>
    <main>@yield('content')</main>
</body>
</html>
