<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\WorkingDays\Update;
use App\Services\WorkingDayService;
use App\Traits\Report;
use Illuminate\Http\JsonResponse;
use Illuminate\Contracts\View\View;

class WorkingDayController extends Controller
{
    public function __construct(private WorkingDayService $workingDayService)
    {
    }

    public function index(): View
    {
        $workingDays = $this->workingDayService->getWorkingDays();

        return view('admin.working_days.index', compact('workingDays'));
    }

    public function update(Update $request): JsonResponse
    {
        $workingDays = $this->workingDayService->updateWorkingDays($request->validated('days'));
        Report::addToLog('تعديل أيام العمل');

        return response()->json([
            'message' => __('admin.update_successfullay'),
            'data' => $workingDays,
        ]);
    }
}
