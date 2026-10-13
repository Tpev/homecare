<?php

namespace App\Http\Controllers;

use App\Models\CaregiverScreeningCase;
use App\Services\Screening\ScreeningAuditService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ScreeningAuditExportController extends Controller
{
    public function __invoke(Request $request, CaregiverScreeningCase $screeningCase, ScreeningAuditService $audit): StreamedResponse
    {
        abort_unless($request->user()?->isAdministrator(), 403);
        $audit->record($screeningCase->id, $request->user()->id, 'audit_downloaded');

        return response()->streamDownload(function () use ($screeningCase, $audit): void {
            foreach ($screeningCase->events()->cursor() as $event) {
                echo json_encode($audit->serialize($event), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)."\n";
            }
        }, 'caregiver-screening-'.$screeningCase->id.'-audit.jsonl', [
            'Content-Type' => 'application/x-ndjson', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
