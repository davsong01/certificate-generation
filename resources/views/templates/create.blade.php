@extends(config('certificates.layout', 'layouts.app'))

@section('title', 'Create Certificate Template')

@section(config('certificates.content_section', 'content'))
<div class="container-fluid py-3">
    <form method="POST" action="{{ route(config('certificates.routes.name', 'certificates.').'manage.templates.store') }}" enctype="multipart/form-data">
        @csrf
        @include('certificates::templates._designer', ['template' => null])
    </form>
</div>
@endsection
