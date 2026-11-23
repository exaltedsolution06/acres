<nav aria-label="breadcrumb"><ol class="breadcrumb small">
    @foreach($crumbs as [$label, $url])
        @if($url && ! $loop->last)<li class="breadcrumb-item"><a href="{{ $url }}">{{ $label }}</a></li>
        @else<li class="breadcrumb-item active" aria-current="page">{{ $label }}</li>@endif
    @endforeach
</ol></nav>
