<?php

namespace App\Http\Controllers;

use App\Services\UserSnapshotService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

/**
 * Generates a downloadable "Personal Report" PDF - a human-readable summary
 * across every module - using the same UserSnapshotService the AI Planner
 * uses, so the two never drift out of sync with each other.
 */
class ReportController extends Controller
{
    public function download(Request $request, UserSnapshotService $snapshotService)
    {
        $user = $request->user();
        $snapshot = $snapshotService->build($user);

        $pdf = Pdf::loadView('reports.personal-report', [
            'user' => $user,
            'snapshot' => $snapshot,
        ])->setPaper('a4');

        return $pdf->download('personal-report-' . now()->format('Y-m-d') . '.pdf');
    }
}
