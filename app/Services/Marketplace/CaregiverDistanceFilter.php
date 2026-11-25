<?php

namespace App\Services\Marketplace;

use App\Models\CaregiverProfile;
use App\Models\CareRequest;

/** ZIP-area estimates for staff invitation discovery, independent of travel preferences. */
class CaregiverDistanceFilter
{
    public const MILES = [5, 10, 25, 50, 100];

    private static ?array $coordinates = null;

    public function hasOrigin(CareRequest $request): bool
    {
        return $this->coordinates($request->zip) !== null;
    }

    /** @return array<string|int, float> Distances keyed by the stored service ZIP. */
    public function within(CareRequest $request, int $miles): array
    {
        $origin = $this->coordinates($request->zip);
        if ($origin === null || ! in_array($miles, self::MILES, true)) {
            return [];
        }

        $distances = [];
        // Resolve the whole pool before discovery applies its result limit. Only
        // ZIPs are read here; eligibility stays in the existing discovery query.
        foreach (CaregiverProfile::query()->whereNotNull('service_area_zip')->distinct()->pluck('service_area_zip') as $zip) {
            $destination = $this->coordinates($zip);
            if ($destination === null) {
                continue;
            }

            $distance = $this->milesBetween($origin, $destination);
            if ($distance <= $miles) {
                $distances[$zip] = $distance;
            }
        }

        return $distances;
    }

    private function coordinates(?string $zip): ?array
    {
        if (! preg_match('/\A([0-9]{5})(?:-[0-9]{4})?\z/', trim((string) $zip), $matches)) {
            return null;
        }

        if (self::$coordinates === null) {
            $rows = fopen(resource_path('geo/us-zcta.csv'), 'r');
            if ($rows === false) {
                throw new \RuntimeException('The bundled ZIP coordinate file is unavailable.');
            }
            $coordinates = [];
            try {
                fgetcsv($rows, 0, ',', '"', '');
                while (($row = fgetcsv($rows, 0, ',', '"', '')) !== false) {
                    $coordinates[$row[0]] = [(float) $row[1], (float) $row[2]];
                }
            } finally {
                fclose($rows);
            }
            self::$coordinates = $coordinates;
        }

        return self::$coordinates[$matches[1]] ?? null;
    }

    private function milesBetween(array $origin, array $destination): float
    {
        $latitude = deg2rad($destination[0] - $origin[0]);
        $longitude = deg2rad($destination[1] - $origin[1]);
        $a = sin($latitude / 2) ** 2
            + cos(deg2rad($origin[0])) * cos(deg2rad($destination[0])) * sin($longitude / 2) ** 2;

        return 3958.7613 * 2 * asin(sqrt(min(1, max(0, $a))));
    }
}
