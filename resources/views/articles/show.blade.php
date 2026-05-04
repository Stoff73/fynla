<!doctype html>
<html lang="en-GB">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">

    <title>{{ $article->title }} — Fynla</title>
    @if($article->description)
        <meta name="description" content="{{ Str::limit($article->description, 160, '') }}">
    @endif
    @if($article->keywords)
        <meta name="keywords" content="{{ $article->keywords }}">
    @endif
    @if($article->author_byline ?? $article->author_name)
        <meta name="author" content="{{ $article->author_byline ?? $article->author_name }}">
    @endif
    <link rel="canonical" href="{{ url('/articles/'.$article->slug) }}">

    <meta property="og:type" content="article">
    <meta property="og:title" content="{{ $article->title }}">
    @if($article->description)
        <meta property="og:description" content="{{ Str::limit($article->description, 160, '') }}">
    @endif
    <meta property="og:url" content="{{ url('/articles/'.$article->slug) }}">
    @if($article->cover_image_path)
        <meta property="og:image" content="{{ asset('storage/'.$article->cover_image_path) }}">
    @endif
    @if($article->published_at)
        <meta property="article:published_time" content="{{ $article->published_at->toIso8601String() }}">
    @endif
    @if($article->author_byline ?? $article->author_name)
        <meta property="article:author" content="{{ $article->author_byline ?? $article->author_name }}">
    @endif

    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $article->title }}">
    @if($article->description)
        <meta name="twitter:description" content="{{ Str::limit($article->description, 160, '') }}">
    @endif
    @if($article->cover_image_path)
        <meta name="twitter:image" content="{{ asset('storage/'.$article->cover_image_path) }}">
    @endif

    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "Article",
        "headline": "{{ addslashes($article->title) }}",
        "description": "{{ addslashes(Str::limit($article->description ?? '', 160, '')) }}",
        "author": { "@type": "Person", "name": "{{ addslashes($article->author_byline ?? $article->author_name ?? 'Fynla') }}" },
        "datePublished": "{{ $article->published_at?->toIso8601String() }}",
        @if($article->cover_image_path)
        "image": "{{ asset('storage/'.$article->cover_image_path) }}"
        @else
        "image": null
        @endif
    }
    </script>

    <style>
        body { font-family: 'Segoe UI', Inter, system-ui, sans-serif; background: #FAF7F2; color: #1F2A44; max-width: 760px; margin: 0 auto; padding: 48px 24px; line-height: 1.7; }
        article h1 { font-weight: 900; font-size: 40px; line-height: 1.15; margin: 0 0 12px; }
        article .subtitle { font-size: 20px; color: #4A5878; margin: 0 0 24px; font-weight: 400; }
        article .byline { font-size: 14px; color: #6B7488; margin: 0 0 32px; display: flex; gap: 12px; align-items: baseline; }
        article .byline time { color: #6B7488; }
        article > img { width: 100%; height: auto; border-radius: 6px; margin: 0 0 32px; }
        article .article-body { font-size: 18px; }
        article .article-body h2 { font-weight: 700; font-size: 28px; margin: 40px 0 16px; }
        article .article-body h3 { font-weight: 700; font-size: 22px; margin: 32px 0 12px; }
        article .article-body p { margin: 0 0 16px; }
        article .article-body img { max-width: 100%; height: auto; border-radius: 4px; }
        article .article-body table { border-collapse: collapse; width: 100%; margin: 16px 0; }
        article .article-body th, article .article-body td { border: 1px solid #E5E1D9; padding: 8px 12px; text-align: left; }
        article .article-body th { background: #F0EBE0; font-weight: 700; }
        article .article-body a { color: #C4225B; text-decoration: underline; }
        article .article-body blockquote { border-left: 4px solid #C4225B; padding: 0 0 0 16px; margin: 16px 0; color: #4A5878; }
    </style>
</head>
<body>
    <article>
        <h1>{{ $article->title }}</h1>
        @if($article->subtitle)
            <p class="subtitle">{{ $article->subtitle }}</p>
        @endif
        <div class="byline">
            @if($article->author_byline ?? $article->author_name)
                <span>{{ $article->author_byline ?? $article->author_name }}</span>
            @endif
            @if($article->published_at)
                <time datetime="{{ $article->published_at->toIso8601String() }}">
                    {{ $article->published_at->format('j F Y') }}
                </time>
            @endif
        </div>
        @if($article->cover_image_path)
            <img src="{{ asset('storage/'.$article->cover_image_path) }}" alt="">
        @endif
        <div class="article-body">{!! $article->html_body !!}</div>
    </article>
</body>
</html>
