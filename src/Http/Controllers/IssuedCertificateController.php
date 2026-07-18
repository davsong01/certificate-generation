<?php

namespace DavidOghi\CertificateGeneration\Http\Controllers;

use DavidOghi\CertificateGeneration\Contracts\CertificateScope;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class IssuedCertificateController extends Controller
{
    public function __invoke(Request $request, CertificateScope $scope)
    {
        $model = config('certificates.models.issued_certificate');
        $query = $scope->apply($model::query())->with(['template', 'recipient', 'issuer'])->latest('issued_at')->latest('id');

        return view('certificates::issued.index', [
            'certificates' => $query->paginate(15)->withQueryString(),
        ]);
    }
}
