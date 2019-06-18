<div class="article detail">
    <img src="{{ $article->thumbnail_url }}" class="img-thumbnail">
    <dl class="mx-1 mt-2">
        <dt>Author / Publisher</dt>
        <dd class="mx-1 mt-2">
            {{ $article->author }} / <a href="#" rel="author">{{ $article->user->name }}</a>
        </dd>
        <dt>Categories</dt>
        <dd class="mx-1 mt-2">
            @include('parts.category-list', ['categories' => $article->categories])
        </dd>
        <dt>Description</dt>
        <dd class="mx-1 mt-2">{{ $article->description }}</dd>
        @if ($article->thanks)
            <dt>Thanks</dt>
            <dd class="mx-1 mt-2">{{ $article->thanks }}</dd>
        @endif
        @if ($article->license)
            <dt>License</dt>
            <dd class="mx-1 mt-2">{{ $article->license }}</dd>
        @endif
        @if ($article->agreement)
            <dt>Agreement</dt>
            <dd class="mx-1 mt-2">The add-on author has given you permission to introduce add-ons (or the author's own post)</dd>
        @endif
        <dt>Download</dt>
        <dd class="mx-1 mt-2"><a href="{{ $article->link }}" target="_blank" rel="noopener nofollow noreferrer">{{ $article->link }}</a></dd>
    </dl>
</div>
