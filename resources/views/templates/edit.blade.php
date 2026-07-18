@extends(config('certificates.layout', 'layouts.app'))

@section('title', 'Edit Certificate Designer')

@section(config('certificates.content_section', 'content'))
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
</style>

<div class="container-fluid py-3 certificate-templates-page">
    <form method="POST" action="{{ route(config('certificates.routes.name', 'certificates.').'manage.templates.update', $template) }}" enctype="multipart/form-data">
        @csrf
        @method('PUT')
        <div class="card mb-3">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-12 mb-3">
                        <label class="form-label fw-bold">Programs</label>
                        <select name="program_ids[]" class="select2 form-control" multiple required data-placeholder="Select programs">
                            @foreach($programs as $program)
                                <option value="{{ $program->id }}" @selected(in_array($program->id, old('program_ids', $selectedProgramIds ?? [])))>
                                    {{ $program->p_name }}
                                </option>
                            @endforeach
                        </select>
                        <small class="text-muted d-block mt-1">Legacy programs are still listed here until they are moved to a different designer template or removed from the selection.</small>
                        @php
                            $selectedPrograms = collect($programs)->whereIn('id', old('program_ids', $selectedProgramIds ?? []))->values();
                            $useBootstrap4 = (int) config('certificates.ui.bootstrap_version', 4) < 5;
                        @endphp
                        @if($selectedPrograms->isNotEmpty())
                            <div class="alert alert-info py-2 px-3 mt-3 mb-0">
                                <strong>Assigned programs:</strong>
                                @foreach($selectedPrograms as $selectedProgram)
                                    <span class="badge {{ $useBootstrap4 ? 'badge-light mr-1' : 'bg-light text-dark me-1' }}">{{ $selectedProgram->p_name }}</span>
                                @endforeach
                            </div>
                        @else
                            <div class="alert alert-light border py-2 px-3 mt-3 mb-0">
                                No programs currently inherit this template.
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
        @include('certificates::templates._designer', ['template' => $template])
    </form>
</div>
@endsection

@section('extra-scripts')
<script>
    $(function () {
        $('.select2').select2({
            width: '100%'
        });
    });
</script>
@endsection
