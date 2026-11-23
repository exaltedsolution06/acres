@if(! empty($badges))
    <div class="d-flex flex-wrap gap-1 mb-2">
        @foreach($badges as [$label, $explain])
            <span class="badge text-bg-success" title="{{ $explain }}" style="cursor:help">&#10003; {{ $label }}</span>
        @endforeach
    </div>
    <div class="small text-muted mb-2">@foreach($badges as [$label, $explain])@if(str_contains($label, 'Verified') || str_contains($label, 'reviewed'))<div>{{ $explain }}</div>@endif @endforeach</div>
@endif
