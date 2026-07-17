@extends(config('certificates.layout', 'layouts.app'))

@section('title', 'Certificate Templates')

@section(config('certificates.content_section', 'content'))
@php($route = config('certificates.routes.name', 'certificates.').'manage.templates')
<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div><h3 class="mb-1">Certificate Templates</h3><div class="text-muted">Design, preview and issue reusable certificates.</div></div>
        <a href="{{ route($route.'.create') }}" class="btn btn-primary">Create Template</a>
    </div>
    @include('certificates::partials.alerts')
    <div class="row g-3 mb-3">
        <div class="col-md-4"><div class="card"><div class="card-body"><div class="text-muted">Templates</div><div class="h3">{{ $summary['total'] }}</div></div></div></div>
        <div class="col-md-4"><div class="card"><div class="card-body"><div class="text-muted">Active</div><div class="h3">{{ $summary['active'] }}</div></div></div></div>
        <div class="col-md-4"><div class="card"><div class="card-body"><div class="text-muted">Issued</div><div class="h3">{{ $summary['issued'] }}</div></div></div></div>
    </div>
    <div class="card">
        <div class="card-header"><form class="row g-2">
            <div class="col-md-5"><input class="form-control" type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search templates"></div>
            @if($modules !== [])<div class="col-md-3"><select class="form-select" name="module"><option value="">All modules</option>@foreach($modules as $key => $label)<option value="{{ $key }}" @selected(($filters['module'] ?? '') === $key)>{{ $label }}</option>@endforeach</select></div>@endif
            <div class="col-md-2"><select class="form-select" name="status"><option value="">Any status</option><option value="1" @selected(($filters['status'] ?? '') === '1')>Active</option><option value="0" @selected(($filters['status'] ?? '') === '0')>Inactive</option></select></div>
            <div class="col-md-2"><button class="btn btn-primary w-100">Filter</button></div>
        </form></div>
        <div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Name</th><th>Modules</th><th>Status</th><th>Issued</th><th></th></tr></thead><tbody>
        @forelse($templates as $template)
            <tr><td><strong>{{ $template->name }}</strong><div class="small text-muted">{{ \Illuminate\Support\Str::limit($template->description, 80) }}</div></td>
                <td>@forelse($template->supported_modules ?? [] as $module)<span class="badge bg-light text-dark me-1">{{ $modules[$module] ?? $module }}</span>@empty All modules @endforelse</td>
                <td><span class="badge {{ $template->status ? 'bg-success' : 'bg-secondary' }}">{{ $template->status ? 'Active' : 'Inactive' }}</span></td>
                <td>{{ $template->issued_certificates_count }}</td>
                <td class="text-end"><div class="d-flex gap-1 justify-content-end">
                    <a class="btn btn-sm btn-light" href="{{ route($route.'.edit', $template) }}">Edit</a>
                    <form method="POST" action="{{ route($route.'.duplicate', $template) }}">@csrf<button class="btn btn-sm btn-light">Duplicate</button></form>
                    <form method="POST" action="{{ route($route.'.destroy', $template) }}" onsubmit="return confirm('Delete this template?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">Delete</button></form>
                </div></td></tr>
        @empty <tr><td colspan="5" class="text-center text-muted py-4">No templates found.</td></tr> @endforelse
        </tbody></table></div>
        <div class="card-footer">{{ $templates->links() }}</div>
    </div>
</div>
@endsection
