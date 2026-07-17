@extends(config('certificates.layout', 'layouts.app'))

@section('title', 'Certificate Templates')

@section(config('certificates.content_section', 'content'))
@php($useBootstrap4 = (int) config('certificates.ui.bootstrap_version', 4) < 5)
@php($route = config('certificates.routes.name', 'certificates.').'manage.templates')
<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center mb-2">
        <div><h3 class="mb-1">Certificate Templates</h3><div class="text-muted">Design, preview and issue reusable certificates.</div></div>
        <a href="{{ route($route.'.create') }}" class="btn btn-primary">Create Template</a>
    </div>
    @include('certificates::partials.alerts')
    <div class="row" style="margin-left:-6px;margin-right:-6px;margin-bottom:12px;">
        <div class="col-md-4" style="padding-left:6px;padding-right:6px;"><div class="card"><div class="card-body py-2"><div class="text-muted">Templates</div><div class="h3 mb-0">{{ $summary['total'] }}</div></div></div></div>
        <div class="col-md-4" style="padding-left:6px;padding-right:6px;"><div class="card"><div class="card-body py-2"><div class="text-muted">Active</div><div class="h3 mb-0">{{ $summary['active'] }}</div></div></div></div>
        <div class="col-md-4" style="padding-left:6px;padding-right:6px;"><div class="card"><div class="card-body py-2"><div class="text-muted">Issued</div><div class="h3 mb-0">{{ $summary['issued'] }}</div></div></div></div>
    </div>
    <div class="card">
        <div class="card-header py-2"><form class="row">
            <div class="col-md-5 mb-2 mb-md-0"><input class="form-control form-control-sm" type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search templates"></div>
            @if($modules !== [])<div class="col-md-3 mb-2 mb-md-0"><select class="form-control form-control-sm" name="module"><option value="">All modules</option>@foreach($modules as $key => $label)<option value="{{ $key }}" @selected(($filters['module'] ?? '') === $key)>{{ $label }}</option>@endforeach</select></div>@endif
            <div class="col-md-2 mb-2 mb-md-0"><select class="form-control form-control-sm" name="status"><option value="">Any status</option><option value="1" @selected(($filters['status'] ?? '') === '1')>Active</option><option value="0" @selected(($filters['status'] ?? '') === '0')>Inactive</option></select></div>
            <div class="col-md-2"><button class="btn btn-primary btn-sm w-100">Filter</button></div>
        </form></div>
        <div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Name</th><th>Programs</th><th>Modules</th><th>Status</th><th>Issued</th><th></th></tr></thead><tbody>
        @forelse($templates as $template)
            <tr><td><strong>{{ $template->name }}</strong><div class="small text-muted">{{ \Illuminate\Support\Str::limit($template->description, 80) }}</div></td>
                <td>@forelse($template->programs as $program)<span class="badge {{ $useBootstrap4 ? 'badge-light mr-1' : 'bg-light text-dark me-1' }}">{{ $program->p_name }}</span>@empty <span class="text-muted">No programs assigned</span>@endforelse</td>
                <td>@forelse($template->supported_modules ?? [] as $module)<span class="badge {{ $useBootstrap4 ? 'badge-light mr-1' : 'bg-light text-dark me-1' }}">{{ $modules[$module] ?? $module }}</span>@empty All modules @endforelse</td>
                <td><span class="badge {{ $template->status ? 'bg-success' : 'bg-secondary' }}">{{ $template->status ? 'Active' : 'Inactive' }}</span></td>
                <td>{{ $template->issued_certificates_count }}</td>
                <td class="text-end"><div class="d-flex justify-content-end">
                    <a class="btn btn-sm btn-light" href="{{ route($route.'.edit', $template) }}">Edit</a>
                    <form method="POST" action="{{ route($route.'.duplicate', $template) }}" class="ml-1">@csrf<button class="btn btn-sm btn-light">Duplicate</button></form>
                    <form method="POST" action="{{ route($route.'.destroy', $template) }}" onsubmit="return confirm('Delete this template?')" class="ml-1">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">Delete</button></form>
                </div></td></tr>
        @empty <tr><td colspan="6" class="text-center text-muted py-4">No templates found.</td></tr> @endforelse
        </tbody></table></div>
        <div class="card-footer">{{ $templates->links() }}</div>
    </div>
</div>
@endsection
