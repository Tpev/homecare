<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCareAssistanceApplicationRequest;
use App\Models\CareAssistanceApplication;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class CareAssistanceController extends Controller
{
    public function index(): View
    {
        return view('marketing.care-assistance', [
            'relationshipOptions' => config('care_assistance.relationship_options', []),
            'careNeedOptions' => config('care_assistance.care_need_options', []),
            'weeklyHourOptions' => config('care_assistance.weekly_hour_options', []),
            'startTimeframeOptions' => config('care_assistance.start_timeframe_options', []),
            'faqs' => config('care_assistance.faqs', []),
        ]);
    }

    public function store(StoreCareAssistanceApplicationRequest $request): RedirectResponse
    {
        CareAssistanceApplication::query()->create(array_merge(
            $request->safe()->only([
                'name',
                'email',
                'phone',
                'relationship',
                'city',
                'zip',
                'care_needs',
                'weekly_hours',
                'start_timeframe',
                'hardship_details',
            ]),
            [
                'user_id' => $request->user()?->id,
                'consented_at' => now(),
                'status' => 'new',
            ],
        ));

        return redirect()->to(route('care-assistance.index').'#apply')
            ->with('care_assistance_submitted', true);
    }
}
