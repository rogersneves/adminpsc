<?php

declare(strict_types=1);

namespace Modules\Reports\Http\Controllers;

use App\Http\Controllers\Controller;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Modules\Financial\Enums\ChargeStatus;
use Modules\Reports\Actions\BuildFinancialReportAction;
use Modules\Reports\Exports\FinancialExport;
use Modules\Reports\Support\PsychologistPatientScope;

class FinancialReportController extends Controller
{
    public function index(Request $request, BuildFinancialReportAction $action, PsychologistPatientScope $scope): Response
    {
        $this->authorize('reports.view');

        $rows = $action(...$this->filters($request, $scope));

        return Inertia::render('Reports/Financial', ['rows' => $rows]);
    }

    public function exportPdf(Request $request, BuildFinancialReportAction $action, PsychologistPatientScope $scope)
    {
        $this->authorize('reports.view');

        $rows = $action(...$this->filters($request, $scope));

        return Pdf::loadView('reports::pdf.financial', ['rows' => $rows])->download('relatorio-financeiro.pdf');
    }

    public function exportExcel(Request $request, BuildFinancialReportAction $action, PsychologistPatientScope $scope)
    {
        $this->authorize('reports.view');

        $rows = $action(...$this->filters($request, $scope));

        return Excel::download(new FinancialExport($rows), 'relatorio-financeiro.xlsx');
    }

    /**
     * @return array{0: list<string>|null, 1: ?string, 2: ?ChargeStatus, 3: ?CarbonImmutable, 4: ?CarbonImmutable}
     */
    private function filters(Request $request, PsychologistPatientScope $scope): array
    {
        $patientIds = $scope->patientIdsFor($request->user(), $request->input('psychologist_id'));

        return [
            $patientIds,
            $request->input('patient_id'),
            $request->filled('status') ? ChargeStatus::from($request->string('status')->toString()) : null,
            $request->filled('from') ? CarbonImmutable::parse($request->string('from')->toString())->startOfDay() : null,
            $request->filled('to') ? CarbonImmutable::parse($request->string('to')->toString())->endOfDay() : null,
        ];
    }
}
