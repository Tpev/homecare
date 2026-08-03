<?php

namespace App\Http\Controllers;

use App\Models\CaregiverCertification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CaregiverCertificationDocumentController extends Controller
{
    public function __invoke(Request $request, CaregiverCertification $certification): StreamedResponse
    {
        $user = $request->user();
        $certification->loadMissing('caregiverProfile');

        abort_unless(
            $user && ($user->isAdministrator() || (int) $certification->caregiverProfile?->user_id === (int) $user->id),
            403,
        );
        abort_unless(
            $certification->document_path
                && str_starts_with($certification->document_path, 'caregiver-certifications/')
                && Storage::disk('local')->exists($certification->document_path),
            404,
        );

        $name = basename((string) ($certification->document_original_name ?: 'credential-document'));

        return Storage::disk('local')->download($certification->document_path, $name, [
            'Content-Type' => $certification->document_mime ?: 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
