<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\AdminStatsService;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function __invoke(AdminStatsService $stats): JsonResponse
    {
        return $this->success($stats->dashboard(), __('messages.ok'));
    }
}
