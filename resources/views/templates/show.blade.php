@extends(config('certificates.layout', 'layouts.app'))
@section('title', $template->name)
@section(config('certificates.content_section', 'content'))
<div class="container py-3"><div class="card"><div class="card-body">
    <h3>{{ $template->name }}</h3><p>{{ $template->description }}</p>
    <pre class="bg-light border rounded p-3">{{ json_encode($template->settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
</div></div></div>
@endsection
