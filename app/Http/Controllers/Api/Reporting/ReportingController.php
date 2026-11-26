<?php

namespace App\Http\Controllers\Api\Reporting;

use App\Http\Controllers\Controller;
use App\Services\Reporting\ReportingCatalog;
use App\Services\Reporting\ReportingConnection;
use App\Services\Reporting\ReportingExporter;

final class ReportingController extends Controller
{
    private const HEADERS = ['Cache-Control' => 'private, no-store, max-age=0', 'X-Content-Type-Options' => 'nosniff'];

    public function schema(ReportingConnection $connections, ReportingExporter $exporter)
    {
        try {
            return response()->json([
                'schema_version' => ReportingCatalog::VERSION,
                'datasets' => $exporter->catalog($connections->open()),
                'business_json_keys' => ReportingCatalog::businessJsonKeys(),
            ], 200, self::HEADERS);
        } catch (\Throwable) {
            return response()->json(['code' => 'reporting_unavailable'], 503, self::HEADERS);
        } finally {
            $connections->close();
        }
    }

    public function export(ReportingConnection $connections, ReportingExporter $exporter)
    {
        try {
            $db = $connections->open();
            $manifest = $exporter->prepare($db, $connections);
        } catch (\Throwable) {
            $connections->close();

            return response()->json(['code' => 'reporting_unavailable'], 503, self::HEADERS);
        }

        return response()->stream(function () use ($db, $manifest, $connections, $exporter): void {
            try {
                $exporter->stream($db, $manifest, static function (string $line): void {
                    echo $line;
                });
            } catch (\Throwable) {
                // A truncated stream is intentionally unusable. Never emit SQL/customer values.
                echo "{\"type\":\"error\",\"code\":\"export_incomplete\"}\n";
            } finally {
                $connections->close();
            }
        }, 200, self::HEADERS + ['Content-Type' => 'application/x-ndjson; charset=utf-8', 'X-Accel-Buffering' => 'no']);
    }
}
