<div class="mb-3">
    <label class="form-label">I want to use this account as</label>
    <div>
        @foreach($roles as $r)
            <div class="form-check form-check-inline">
                <input class="form-check-input" type="checkbox" name="roles[]" value="{{ $r }}" id="{{ $idPrefix }}-{{ $r }}" @checked(in_array($r, old('roles', [])))>
                <label class="form-check-label" for="{{ $idPrefix }}-{{ $r }}">{{ ucfirst($r) }}</label>
            </div>
        @endforeach
    </div>
    <div class="form-text">You can hold several roles and switch between them any time.</div>
</div>
