<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Plans;

use App\Http\Controllers\Controller;
use App\Services\Plans\AdviserExportPackService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdviserExportPackController extends Controller
{
    public function __construct(
        private readonly AdviserExportPackService $exportPackService,
    ) {}

    public function __invoke(Request $request): Response
    {
        $pack = $this->exportPackService->buildPack($request->user());
        $pdf = Pdf::loadView('plans.adviser-export-pack', ['pack' => $pack])
            ->setPaper('A4', 'portrait');

        return $pdf->download('fynla-adviser-export-'.now()->toDateString().'.pdf');
    }
}
