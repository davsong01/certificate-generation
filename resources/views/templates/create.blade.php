@extends(config('certificates.layout', 'layouts.app'))

@section('title', 'Create Certificate Designer')

@section(config('certificates.content_section', 'content'))
<div class="container-fluid py-3">
    <form method="POST" action="{{ route(config('certificates.routes.name', 'certificates.').'manage.templates.store') }}" enctype="multipart/form-data">
        @csrf
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
                        <small class="text-muted d-block mt-1">These programs will use the new certificate designer for generation.</small>
                    </div>
                </div>
            </div>
        </div>
        @include('certificates::templates._designer', ['template' => null])
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
