@extends(config('certificates.layout', 'layouts.app'))
@section('title', 'Issued Certificates')
@section(config('certificates.content_section', 'content'))
@php($name = config('certificates.routes.name', 'certificates.'))

<div class="container-fluid py-3">
    <h3 class="mb-2">Issued Certificates</h3>
    <div class="card">
        <div class="card-header py-2">Track issued certificates and downloads.</div>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th>Certificate #</th>
                        <th>Recipient</th>
                        <th>Source</th>
                        <th>Template</th>
                        <th>Issued</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($certificates as $certificate)
                        <tr>
                            <td>{{ $certificate->certificate_number }}</td>
                            <td>{{ $certificate->recipient?->name ?? '—' }}</td>
                            <td>{{ \Illuminate\Support\Str::headline($certificate->source_type) }}</td>
                            <td>{{ $certificate->template?->name ?? '—' }}</td>
                            <td>{{ $certificate->issued_at?->format('d M Y H:i') }}</td>
                            <td>
                                @if(config('certificates.routes.download_enabled', true))
                                    <a class="btn btn-sm btn-light" href="{{ route($name.'download', $certificate->certificate_number) }}">Download</a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">No certificates issued.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer">{{ $certificates->links() }}</div>
    </div>
</div>
@endsection
