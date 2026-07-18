@extends(config('certificates.layout', 'layouts.app'))

@section('title', 'Certificate Templates')

@section(config('certificates.content_section', 'content'))
@php($useBootstrap4 = (int) config('certificates.ui.bootstrap_version', 4) < 5)
@php($route = config('certificates.routes.name', 'certificates.').'manage.templates')

<style>
    .certificate-templates-page .badge {
        display: inline-flex !important;
        align-items: center;
        justify-content: center;
        width: auto !important;
        height: auto !important;
        min-width: 0;
        padding: .35rem .6rem;
        border-radius: .35rem !important;
        box-shadow: none;
        font-size: .75rem;
        font-weight: 600;
        line-height: 1.2;
        white-space: nowrap;
    }

    .certificate-templates-page .kpi-card {
        border: 1px solid #e6eaf2;
        border-radius: 10px;
        box-shadow: 0 2px 6px rgba(0, 0, 0, .05);
        background: #fff;
        height: 100%;
    }

    .certificate-templates-page .kpi-value {
        font-size: 1.7rem;
        font-weight: 800;
        line-height: 1;
        color: #0f172a;
    }

    .certificate-templates-page .kpi-label {
        font-size: .78rem;
        text-transform: uppercase;
        letter-spacing: .08em;
        color: #64748b;
        font-weight: 700;
    }

    .certificate-templates-page .filters-card,
    .certificate-templates-page .table-card {
        border: 1px solid #e6eaf2;
        border-radius: 10px;
        box-shadow: 0 2px 6px rgba(0, 0, 0, .05);
        overflow: hidden;
    }

    .certificate-templates-page .table thead th {
        border-top: 0;
        background: #f8fafc;
        color: #475569;
        font-size: .78rem;
        text-transform: uppercase;
        letter-spacing: .06em;
    }

    .certificate-templates-page .table tbody tr {
        transition: background .15s ease, transform .15s ease;
    }

    .certificate-templates-page .table tbody tr:hover {
        background: #f8fbff;
    }

    .certificate-templates-page .template-preview {
        width: 92px;
        height: 58px;
        object-fit: cover;
        border-radius: 8px;
        border: 1px solid #d8e0ea;
        background: #f8fafc;
    }

    .certificate-templates-page .summary-strip {
        display: flex;
        flex-wrap: wrap;
        gap: .75rem;
    }

    .certificate-templates-page .summary-item {
        min-width: 150px;
        flex: 1 1 0;
        border: 1px solid #e6eaf2;
        border-radius: 10px;
        background: #fff;
        padding: .85rem 1rem;
    }

    .certificate-templates-page .action-stack {
        display: flex;
        flex-wrap: wrap;
        gap: .35rem;
        justify-content: flex-end;
    }

    .certificate-templates-page .action-stack .btn {
        border-radius: 10px;
    }
</style>

<div class="container-fluid py-2 py-md-3 certificate-templates-page">
    <div class="card mb-3">
        <div class="card-body py-3 py-md-4">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                <div>
                    <div class="badge badge-primary mb-2">Certificate Designer</div>
                    <h3 class="h5 mb-1">Certificate Templates</h3>
                    <div class="text-muted small" style="max-width: 46rem;">
                        Manage reusable templates and assign programs to the new certificate designer.
                    </div>
                </div>
                <a href="{{ route($route.'.create') }}" class="btn btn-outline-primary btn-sm">Create Template</a>
            </div>
        </div>
    </div>

    <div class="summary-strip mb-3">
        <div class="summary-item">
            <div class="kpi-label mb-1">Total templates</div>
            <div class="kpi-value">{{ number_format($summary['total'] ?? 0) }}</div>
        </div>
        <div class="summary-item">
            <div class="kpi-label mb-1">Active templates</div>
            <div class="kpi-value">{{ number_format($summary['active'] ?? 0) }}</div>
        </div>
        <div class="summary-item">
            <div class="kpi-label mb-1">Issued certificates</div>
            <div class="kpi-value">{{ number_format($summary['issued'] ?? 0) }}</div>
        </div>
        <div class="summary-item">
            <div class="kpi-label mb-1">Assigned programs</div>
            <div class="kpi-value">{{ number_format($summary['assigned_programs'] ?? 0) }}</div>
        </div>
    </div>

    @include('certificates::partials.alerts')

    <div class="card filters-card mb-3">
        <div class="card-body py-3">
            <form class="row align-items-end">
                <div class="col-md-6 mb-2 mb-md-0">
                    <label class="sr-only" for="certificate-template-search">Search templates</label>
                    <input id="certificate-template-search" class="form-control form-control-sm" type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search by name or description">
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
                    <button class="btn btn-primary btn-sm btn-block">Filter</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card table-card">
        <div class="table-responsive">
            <table class="table table-sm mb-0 align-middle">
                <thead class="thead-light">
                    <tr>
                        <th>Name</th>
                        <th>Background</th>
                        <th>Programs</th>
                        <th>Modules</th>
                        <th>Status</th>
                        <th>Issued</th>
                        <th class="text-right"></th>
                    </tr>
                </thead>
                <tbody>
                @if($templates->isNotEmpty())
                    @foreach($templates as $template)
                    @php($supportedModules = $template->supported_modules ?? [])
                    <tr>
                        <td>
                            <strong>{{ $template->name }}</strong>
                            <div class="small text-muted">{{ \Illuminate\Support\Str::limit($template->display_description ?? $template->description, 90) }}</div>
                        </td>
                        <td>
                            @if($template->certificate_template)
                                <div class="d-flex align-items-center">
                                    <div class="template-preview mr-2 d-flex align-items-center justify-content-center">
                                        <i class="ti ti-photo text-muted"></i>
                                    </div>
                                    <div>
                                        <div class="small text-muted mb-1">{{ basename($template->certificate_template) }}</div>
                                        <a class="btn btn-outline-primary btn-sm" href="{{ route($route.'.download', $template) }}">Download</a>
                                    </div>
                                </div>
                            @else
                                <span class="text-muted">No background file</span>
                            @endif
                        </td>
                        <td>
                            @if($template->programs->isNotEmpty())
                                @foreach($template->programs as $program)
                                    <span class="badge {{ $useBootstrap4 ? 'badge-light mr-1' : 'bg-light text-dark me-1' }}">{{ $program->p_name }}</span>
                                @endforeach
                            @else
                                <span class="text-muted">No programs assigned</span>
                            @endif
                        </td>
                        <td>
                            @if(! empty($supportedModules))
                                @foreach($supportedModules as $module)
                                    <span class="badge {{ $useBootstrap4 ? 'badge-light mr-1' : 'bg-light text-dark me-1' }}">{{ $modules[$module] ?? $module }}</span>
                                @endforeach
                            @else
                                <span class="text-muted">All modules</span>
                            @endif
                        </td>
                        <td>
                            <span class="badge {{ $template->status ? 'badge-success' : 'badge-secondary' }}">{{ $template->status ? 'Active' : 'Inactive' }}</span>
                        </td>
                        <td>{{ $template->issued_certificates_count }}</td>
                        <td class="text-right">
                            <div class="action-stack">
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
                    @endforeach
                @else
                    <tr>
                        <td colspan="7" class="text-center text-muted py-4">No templates found.</td>
                    </tr>
                @endif
                </tbody>
            </table>
        </div>

        <div class="card-footer py-2">
            {{ $templates->links() }}
        </div>
    </div>
</div>
@endsection
