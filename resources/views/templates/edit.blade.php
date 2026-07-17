@extends(config('certificates.layout', 'layouts.app'))

@section('title', 'Edit Certificate Template')

@section(config('certificates.content_section', 'content'))
<div class="container-fluid py-3">
    <form method="POST" action="{{ route(config('certificates.routes.name', 'certificates.').'manage.templates.update', $template) }}" enctype="multipart/form-data">
        @csrf
        @method('PUT')
        @include('certificates::templates._designer', ['template' => $template])
    </form>
</div>
@endsection
