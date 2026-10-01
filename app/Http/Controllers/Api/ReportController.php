<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreReportRequest;
use App\Http\Resources\ReportResource;
use App\Models\Report;
use App\Services\ReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    /** Reports the authenticated user has submitted. */
    public function index(Request $request): JsonResponse
    {
        $reports = Report::query()
            ->where('reporter_id', $request->user()->id)
            ->latest()
            ->paginate($this->perPage());

        return $this->paginated($reports, __('messages.ok'), ReportResource::class);
    }

    public function store(StoreReportRequest $request, ReportService $reports): JsonResponse
    {
        $this->authorize('create', Report::class);

        $report = $reports->create($request->user(), $request->validated());

        return $this->created(new ReportResource($report), __('messages.report_created'));
    }
}
