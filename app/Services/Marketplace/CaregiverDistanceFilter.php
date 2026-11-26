<?php

namespace App\Services\Marketplace;

use App\Models\CaregiverProfile;
use App\Models\CareRequest;
use Illuminate\Database\Eloquent\Builder;

/** ZIP-area estimates for browsing, independent of saved travel preferences. */
class CaregiverDistanceFilter
{
    public const MILES = [5, 10, 25, 50, 100];

    private static ?array $coordinates = null;

    public function hasOrigin(CareRequest $request): bool
    {
        return $this->hasZip($request->zip);
    }

    public function hasZip(?string $zip): bool
    {
        return $this->coordinates($zip) !== null;
    }

    /** @return array<string|int, float> Distances keyed by the stored service ZIP. */
    public function within(CareRequest $request, int $miles): array
    {
        if (! in_array($miles, self::MILES, true)) {
            return [];
        }

        // Resolve the whole pool before discovery applies its result limit. Only
        // ZIPs are read here; eligibility stays in the existing discovery query.
        return array_filter($this->distancesFrom($request->zip,
            CaregiverProfile::query()->whereNotNull('service_area_zip')->distinct()->pluck('service_area_zip')
        ), fn (float $distance) => $distance <= $miles);
    }

    /** Filter the existing visibility query before pagination; return distances for card labels. */
    public function filterRequests(Builder $query, ?string $originZip, ?int $miles): array
    {
        $zips = (clone $query)->reorder()->select('care_requests.zip')->distinct()->pluck('care_requests.zip');
        $distances = $this->distancesFrom($originZip, $zips);
        if ($miles !== null) {
            $matching = in_array($miles, self::MILES, true)
                ? array_filter($distances, fn (float $distance) => $distance <= $miles)
                : [];
            $query->whereIn('care_requests.zip', array_map('strval', array_keys($matching)));
        }

        return $distances;
    }

    private function distancesFrom(?string $originZip, iterable $zips): array
    {
        $origin = $this->coordinates($originZip);
        if ($origin === null) {
            return [];
        }

        $distances = [];
        foreach ($zips as $zip) {
            $destination = $this->coordinates($zip);
            if ($destination === null) {
                continue;
            }

            $distances[$zip] = $this->milesBetween($origin, $destination);
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
