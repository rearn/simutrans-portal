<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        @foreach ($breadcrumb as $bread)
            @if(isset($bread['url']))
                <li class="breadcrumb-item"><a href="{{ $bread['url'] }}">{{ $bread['name'] }}</a></li>
            @else
                <li class="breadcrumb-item" aria-current="page">{{ $bread['name'] }}</li>
            @endif
        @endforeach
    </ol>
</nav>
