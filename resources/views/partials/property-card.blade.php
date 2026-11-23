<div class="card h-100 shadow-sm">
    <a href="{{ route('property.show', $p->slug) }}">
        @if($p->cover)<img src="{{ $p->cover->thumbUrl() }}" alt="{{ $p->cover->alt }}" class="card-img-top ph" loading="lazy">
        @else<div class="card-img-top ph d-flex align-items-center justify-content-center text-muted">No photo</div>@endif
    </a>
    <div class="card-body">
        @if($p->isFeatured())<span class="badge text-bg-warning mb-1">Featured</span>@endif
        <div class="fw-semibold fs-5">{{ $p->displayPrice() }}</div>
        <a class="text-decoration-none text-dark" href="{{ route('property.show', $p->slug) }}"><h2 class="h6 mb-1">{{ $p->title }}</h2></a>
        <div class="small text-muted">{{ $p->typeLabel() }} · {{ $p->locality->name }}, {{ $p->city->name }}</div>
        <div class="small mt-1">
            @if($p->areaSqft()){{ number_format($p->areaSqft()) }} sq ft @endif
            @if($p->furnishing)· {{ config('property.furnishing')[$p->furnishing] ?? '' }}@endif
        </div>
    </div>
</div>
