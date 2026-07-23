<?php

declare(strict_types=1);

namespace Modules\Reports\Http\Controllers;

use App\Http\Controllers\Controller;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Modules\Reports\Actions\BuildSessionsReportAction;
use Modules\Reports\Exports\SessionsExport;
use Modules\Reports\Support\PsychologistPatientScope;

class SessionsReportController extends Controller
{
    public function index(Request $request, BuildSessionsReportAction $action, PsychologistPatientScope $scope): Response
    {
        $this->authorize('reports.view');

        $rows = $action(...$this->filters($request, $scope));

        return Inertia::render('Reports/Sessions', ['rows' => $rows]);
    }

    public function exportPdf(Request $request, BuildSessionsReportAction $action, PsychologistPatientScope $scope): RedirectResponse|\Illuminate\Http\Response
    {
        $this->authorize('reports.view');

        $rows = $action(...$this->filters($request, $scope));

        return Pdf::loadView('reports::pdf.sessions', ['rows' => $rows])->download('relatorio-sessoes.pdf');
    }

    public function exportExcel(Request $request, BuildSessionsReportAction $action, PsychologistPatientScope $scope)
    {
        $this->authorize('reports.view');

        $rows = $action(...$this->filters($request, $scope));

        return Excel::download(new SessionsExport($rows), 'relatorio-sessoes.xlsx');
    }

    /**
     * @return array{0: list<string>|null, 1: ?string, 2: ?CarbonImmutable, 3: ?CarbonImmutable}
     */
    private function filters(Request $request, PsychologistPatientScope $scope): array
    {
        $patientIds = $scope->patientIdsFor($request->user(), $request->input('psychologist_id'));

        return [
            $patientIds,
            $request->input('patient_id'),
            $request->filled('from') ? CarbonImmutable::parse($request->string('from')->toString())->startOfDay() : null,
            $request->filled('to') ? CarbonImmutable::parse($request->string('to')->toString())->endOfDay() : null,
        ];
    }
}
