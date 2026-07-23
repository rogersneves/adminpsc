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
use Modules\Reports\Actions\BuildAttendanceReportAction;
use Modules\Reports\Exports\AttendanceExport;
use Modules\Reports\Support\PsychologistPatientScope;

class AttendanceReportController extends Controller
{
    public function index(Request $request, BuildAttendanceReportAction $action, PsychologistPatientScope $scope): Response
    {
        $this->authorize('reports.view');

        $rows = $action(...$this->filters($request, $scope));

        return Inertia::render('Reports/Attendance', ['rows' => $rows]);
    }

    public function exportPdf(Request $request, BuildAttendanceReportAction $action, PsychologistPatientScope $scope)
    {
        $this->authorize('reports.view');

        $rows = $action(...$this->filters($request, $scope));

        return Pdf::loadView('reports::pdf.attendance', ['rows' => $rows])->download('relatorio-comparecimento.pdf');
    }

    public function exportExcel(Request $request, BuildAttendanceReportAction $action, PsychologistPatientScope $scope)
    {
        $this->authorize('reports.view');

        $rows = $action(...$this->filters($request, $scope));

        return Excel::download(new AttendanceExport($rows), 'relatorio-comparecimento.xlsx');
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
