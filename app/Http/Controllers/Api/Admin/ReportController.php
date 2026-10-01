<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\ReportReason;
use App\Enums\ReportStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateReportRequest;
use App\Http\Resources\ReportResource;
use App\Models\Report;
use App\Services\ReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ReportController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Report::class);
        $request->validate([
            'status' => ['nullable', Rule::enum(ReportStatus::class)],
            'reason' => ['nullable', Rule::enum(ReportReason::class)],
        ]);

        $reports = Report::query()
            ->with(['reporter', 'reportedUser', 'reviewer'])
            ->when($request->input('status'), fn ($q, $status) => $q->where('status', $status))
            ->when($request->input('reason'), fn ($q, $reason) => $q->where('reason', $reason))
            ->latest('id')
            ->paginate($this->perPage(20));

        return $this->paginated($reports, __('messages.ok'), ReportResource::class);
    }

    public function show(Report $report): JsonResponse
    {
        $this->authorize('view', $report);

        $report->load(['reporter', 'reportedUser', 'reviewer', 'trip.owner', 'booking']);

        return $this->success(new ReportResource($report), __('messages.ok'));
    }

    public function update(UpdateReportRequest $request, Report $report, ReportService $reports): JsonResponse
    {
        $this->authorize('update', $report);

        $report = $reports->review(
            $report,
            $request->user(),
            ReportStatus::from($request->input('status')),
            $request->input('admin_notes'),
        );

        return $this->updated(new ReportResource($report->load(['reporter', 'reportedUser', 'reviewer'])), __('messages.report_updated'));
    }
}
