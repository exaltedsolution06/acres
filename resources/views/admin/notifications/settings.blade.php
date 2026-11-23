@extends('layouts.admin')
@section('title', 'Notification settings')
@section('content')
<h1 class="h4 mb-3">Notification settings</h1>
<div class="alert alert-info small">Switch each event on or off per channel. Email is only sent to users who have an email address. Email delivery uses the queue and your SMTP settings.</div>
<form method="POST" action="{{ route('admin.notifications.update') }}">@csrf @method('PUT')
<div class="card shadow-sm"><table class="table align-middle mb-0">
    <thead><tr><th>Event</th><th class="text-center">In-app</th><th class="text-center">Email</th></tr></thead><tbody>
    @foreach($events as $key => $label)
        <tr><td>{{ $label }}<div><code>{{ $key }}</code></div></td>
            <td class="text-center"><input class="form-check-input" type="checkbox" name="in_app[{{ $key }}]" value="1" @checked($settings[$key]->in_app ?? true)></td>
            <td class="text-center"><input class="form-check-input" type="checkbox" name="email[{{ $key }}]" value="1" @checked($settings[$key]->email ?? true)></td></tr>
    @endforeach
    </tbody></table></div>
<button class="btn btn-primary mt-3">Save</button>
</form>
@endsection
