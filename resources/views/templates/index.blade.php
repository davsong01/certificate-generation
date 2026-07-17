@extends(config('certificates.layout', 'layouts.app'))

@section('title', 'Certificate Templates')

@section(config('certificates.content_section', 'content'))
@php($useBootstrap4 = (int) config('certificates.ui.bootstrap_version', 4) < 5)
@php($route = config('certificates.routes.name', 'certificates.').'manage.templates')

<div class="container-fluid py-2 py-md-3">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-2">
        <div class="mb-2 mb-md-0">
            <h3 class="h5 mb-1">Certificate Templates</h3>
            <div class="text-muted small">Manage templates for the new certificate designer.</div>
        </div>
        <a href="{{ route($route.'.create') }}" class="btn btn-primary btn-sm">Create Template</a>
    </div>

    @include('certificates::partials.alerts')

    <div class="card">
        <div class="card-body py-2">
            <form class="row align-items-end">
                <div class="col-md-6 mb-2 mb-md-0">
                    <label class="sr-only" for="certificate-template-search">Search templates</label>
                    <input id="certificate-template-search" class="form-control form-control-sm" type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search templates">
                </div>
                @if($modules !== [])
                    <div class="col-md-3 mb-2 mb-md-0">
                        <label class="sr-only" for="certificate-template-module">Module</label>
                        <select id="certificate-template-module" class="form-control form-control-sm" name="module">
                            <option value="">All modules</option>
                            @foreach($modules as $key => $label)
                                <option value="{{ $key }}" @selected(($filters['module'] ?? '') === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
                <div class="col-md-2 mb-2 mb-md-0">
                    <label class="sr-only" for="certificate-template-status">Status</label>
                    <select id="certificate-template-status" class="form-control form-control-sm" name="status">
                        <option value="">Any status</option>
                        <option value="1" @selected(($filters['status'] ?? '') === '1')>Active</option>
                        <option value="0" @selected(($filters['status'] ?? '') === '0')>Inactive</option>
                    </select>
                </div>
                <div class="col-md-1">
                    <button class="btn btn-primary btn-sm btn-block">Go</button>
                </div>
            </form>
        </div>

        <div class="table-responsive">
            <table class="table table-sm mb-0 align-middle">
                <thead class="thead-light">
                    <tr>
                        <th>Name</th>
                        <th>Programs</th>
                        <th>Modules</th>
                        <th>Status</th>
                        <th>Issued</th>
                        <th class="text-right"></th>
                    </tr>
                </thead>
                <tbody>
                @forelse($templates as $template)
                    <tr>
                        <td>
                            <strong>{{ $template->name }}</strong>
                            <div class="small text-muted">{{ \Illuminate\Support\Str::limit($template->description, 70) }}</div>
                        </td>
                        <td>
                            @forelse($template->programs as $program)
                                <span class="badge {{ $useBootstrap4 ? 'badge-light mr-1' : 'bg-light text-dark me-1' }}">{{ $program->p_name }}</span>
                            @empty
                                <span class="text-muted">No programs assigned</span>
                            @endforelse
                        </td>
                        <td>
                            @forelse($template->supported_modules ?? [] as $module)
                                <span class="badge {{ $useBootstrap4 ? 'badge-light mr-1' : 'bg-light text-dark me-1' }}">{{ $modules[$module] ?? $module }}</span>
                            @empty
                                <span class="text-muted">All modules</span>
                            @endforelse
                        </td>
                        <td>
                            <span class="badge {{ $template->status ? 'badge-success' : 'badge-secondary' }}">{{ $template->status ? 'Active' : 'Inactive' }}</span>
                        </td>
                        <td>{{ $template->issued_certificates_count }}</td>
                        <td class="text-right">
                            <div class="btn-group btn-group-sm" role="group">
                                <a class="btn btn-light" href="{{ route($route.'.edit', $template) }}">Edit</a>
                                <form method="POST" action="{{ route($route.'.duplicate', $template) }}">
                                    @csrf
                                    <button class="btn btn-light" type="submit">Duplicate</button>
                                </form>
                                <form method="POST" action="{{ route($route.'.destroy', $template) }}" onsubmit="return confirm('Delete this template?')">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-outline-danger" type="submit">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">No templates found.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div class="card-footer py-2">
            {{ $templates->links() }}
        </div>
    </div>
</div>
@endsection
