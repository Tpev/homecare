<?php

namespace App\Livewire\Admin;

use App\Models\CareBooking;
use App\Models\User;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class CaregiverCoverageMap extends Component
{
    public int $days = 30;
    public string $metric = 'registered';

    protected array $allowedDays = [7, 14, 30, 60, 90, 180, 365];
    protected array $allowedMetrics = ['registered', 'active', 'completed_shifts'];

    protected $queryString = [
        'days' => ['except' => 30],
        'metric' => ['except' => 'registered'],
    ];

    /**
     * @var array<string, string>
     */
    private const US_STATE_NAMES = [
        'AL' => 'Alabama', 'AK' => 'Alaska', 'AZ' => 'Arizona', 'AR' => 'Arkansas', 'CA' => 'California',
        'CO' => 'Colorado', 'CT' => 'Connecticut', 'DE' => 'Delaware', 'FL' => 'Florida', 'GA' => 'Georgia',
        'HI' => 'Hawaii', 'ID' => 'Idaho', 'IL' => 'Illinois', 'IN' => 'Indiana', 'IA' => 'Iowa',
        'KS' => 'Kansas', 'KY' => 'Kentucky', 'LA' => 'Louisiana', 'ME' => 'Maine', 'MD' => 'Maryland',
        'MA' => 'Massachusetts', 'MI' => 'Michigan', 'MN' => 'Minnesota', 'MS' => 'Mississippi', 'MO' => 'Missouri',
        'MT' => 'Montana', 'NE' => 'Nebraska', 'NV' => 'Nevada', 'NH' => 'New Hampshire', 'NJ' => 'New Jersey',
        'NM' => 'New Mexico', 'NY' => 'New York', 'NC' => 'North Carolina', 'ND' => 'North Dakota', 'OH' => 'Ohio',
        'OK' => 'Oklahoma', 'OR' => 'Oregon', 'PA' => 'Pennsylvania', 'RI' => 'Rhode Island', 'SC' => 'South Carolina',
        'SD' => 'South Dakota', 'TN' => 'Tennessee', 'TX' => 'Texas', 'UT' => 'Utah', 'VT' => 'Vermont',
        'VA' => 'Virginia', 'WA' => 'Washington', 'WV' => 'West Virginia', 'WI' => 'Wisconsin', 'WY' => 'Wyoming',
        'DC' => 'District of Columbia',
    ];

    /**
     * @var array<string, array{0: float, 1: float}>
     */
    private const US_STATE_CENTROIDS = [
        'AL' => [32.806671, -86.791130], 'AK' => [61.370716, -152.404419], 'AZ' => [33.729759, -111.431221], 'AR' => [34.969704, -92.373123],
        'CA' => [36.116203, -119.681564], 'CO' => [39.059811, -105.311104], 'CT' => [41.597782, -72.755371], 'DE' => [39.318523, -75.507141],
        'FL' => [27.766279, -81.686783], 'GA' => [33.040619, -83.643074], 'HI' => [21.094318, -157.498337], 'ID' => [44.240459, -114.478828],
        'IL' => [40.349457, -88.986137], 'IN' => [39.849426, -86.258278], 'IA' => [42.011539, -93.210526], 'KS' => [38.526600, -96.726486],
        'KY' => [37.668140, -84.670067], 'LA' => [31.169546, -91.867805], 'ME' => [44.693947, -69.381927], 'MD' => [39.063946, -76.802101],
        'MA' => [42.230171, -71.530106], 'MI' => [43.326618, -84.536095], 'MN' => [45.694454, -93.900192], 'MS' => [32.741646, -89.678696],
        'MO' => [38.456085, -92.288368], 'MT' => [46.921925, -110.454353], 'NE' => [41.125370, -98.268082], 'NV' => [38.313515, -117.055374],
        'NH' => [43.452492, -71.563896], 'NJ' => [40.298904, -74.521011], 'NM' => [34.840515, -106.248482], 'NY' => [42.165726, -74.948051],
        'NC' => [35.630066, -79.806419], 'ND' => [47.528912, -99.784012], 'OH' => [40.388783, -82.764915], 'OK' => [35.565342, -96.928917],
        'OR' => [43.933640, -120.558304], 'PA' => [40.590752, -77.209755], 'RI' => [41.680893, -71.511780], 'SC' => [33.856892, -80.945007],
        'SD' => [44.299782, -99.438828], 'TN' => [35.747845, -86.692345], 'TX' => [31.054487, -97.563461], 'UT' => [40.150032, -111.862434],
        'VT' => [44.045876, -72.710686], 'VA' => [37.769337, -78.169968], 'WA' => [47.400902, -121.490494], 'WV' => [38.491226, -80.954453],
        'WI' => [44.268543, -89.616508], 'WY' => [42.755966, -107.302490], 'DC' => [38.907200, -77.036900],
    ];

    public function mount(): void
    {
        $this->days = in_array($this->days, $this->allowedDays, true) ? $this->days : 30;
        $this->metric = in_array($this->metric, $this->allowedMetrics, true) ? $this->metric : 'registered';
    }

    public function getStartProperty(): Carbon
    {
        return now()->subDays($this->days)->startOfDay();
    }

    public function render()
    {
        $this->days = in_array($this->days, $this->allowedDays, true) ? $this->days : 30;
        $this->metric = in_array($this->metric, $this->allowedMetrics, true) ? $this->metric : 'registered';

        $stateRows = $this->stateRows();
        $cityRows = $this->cityRows();

        $maxCount = max(
            1,
            ...$stateRows->map(fn ($row): int => (int) $row->total)->all(),
        );

        $points = $stateRows
            ->map(function ($row) use ($maxCount): ?array {
                $stateCode = strtoupper((string) $row->state_code);
                $centroid = self::US_STATE_CENTROIDS[$stateCode] ?? null;

                if ($centroid === null) {
                    return null;
                }

                $count = (int) $row->total;
                $intensity = $maxCount > 0 ? ($count / $maxCount) : 0.0;

                return [
                    'state_code' => $stateCode,
                    'state_name' => self::US_STATE_NAMES[$stateCode] ?? $stateCode,
                    'lat' => $centroid[0],
                    'lng' => $centroid[1],
                    'count' => $count,
                    'intensity' => round($intensity, 4),
                    'color' => $this->colorForIntensity($intensity),
                    'radius' => max(16, min(44, (int) round(16 + (sqrt($count) / sqrt($maxCount)) * 28))),
                ];
            })
            ->filter()
            ->values()
            ->all();

        $total = (int) $stateRows->sum('total');
        $statesWithCoverage = $stateRows->count();
        $topState = $stateRows->first();

        $metricMeta = $this->metricMeta();
        $tileMeta = $this->tileMeta();

        return view('livewire.admin.caregiver-coverage-map', [
            'start' => $this->start,
            'metricMeta' => $metricMeta,
            'summary' => [
                'total' => $total,
                'states_with_coverage' => $statesWithCoverage,
                'top_state' => $topState
                    ? [
                        'code' => strtoupper((string) $topState->state_code),
                        'name' => self::US_STATE_NAMES[strtoupper((string) $topState->state_code)] ?? strtoupper((string) $topState->state_code),
                        'count' => (int) $topState->total,
                    ]
                    : null,
                'peak_count' => $maxCount,
            ],
            'mapPayload' => [
                'points' => $points,
                'tile_url' => $tileMeta['tile_url'],
                'tile_attribution' => $tileMeta['tile_attribution'],
                'provider' => $tileMeta['provider'],
            ],
            'cityRows' => $cityRows,
            'daysOptions' => $this->allowedDays,
            'metricOptions' => [
                ['value' => 'registered', 'label' => 'Caregiver signups'],
                ['value' => 'active', 'label' => 'Active caregivers'],
                ['value' => 'completed_shifts', 'label' => 'Completed shifts'],
            ],
        ]);
    }

    private function stateRows()
    {
        return match ($this->metric) {
            'active' => User::query()
                ->join('caregiver_profiles', 'caregiver_profiles.user_id', '=', 'users.id')
                ->where('users.role', 'caregiver')
                ->where('users.created_at', '>=', $this->start)
                ->where('caregiver_profiles.status', 'active')
                ->whereNotNull('users.state')
                ->where('users.state', '!=', '')
                ->selectRaw('UPPER(users.state) as state_code, COUNT(DISTINCT users.id) as total')
                ->groupByRaw('UPPER(users.state)')
                ->orderByDesc('total')
                ->get(),
            'completed_shifts' => CareBooking::query()
                ->join('users', 'users.id', '=', 'care_bookings.caregiver_user_id')
                ->where('users.role', 'caregiver')
                ->whereNotNull('care_bookings.completed_at')
                ->where('care_bookings.completed_at', '>=', $this->start)
                ->whereNotNull('users.state')
                ->where('users.state', '!=', '')
                ->selectRaw('UPPER(users.state) as state_code, COUNT(care_bookings.id) as total')
                ->groupByRaw('UPPER(users.state)')
                ->orderByDesc('total')
                ->get(),
            default => User::query()
                ->where('role', 'caregiver')
                ->where('created_at', '>=', $this->start)
                ->whereNotNull('state')
                ->where('state', '!=', '')
                ->selectRaw('UPPER(state) as state_code, COUNT(id) as total')
                ->groupByRaw('UPPER(state)')
                ->orderByDesc('total')
                ->get(),
        };
    }

    private function cityRows()
    {
        return match ($this->metric) {
            'active' => User::query()
                ->join('caregiver_profiles', 'caregiver_profiles.user_id', '=', 'users.id')
                ->where('users.role', 'caregiver')
                ->where('users.created_at', '>=', $this->start)
                ->where('caregiver_profiles.status', 'active')
                ->whereNotNull('users.city')
                ->where('users.city', '!=', '')
                ->whereNotNull('users.state')
                ->where('users.state', '!=', '')
                ->selectRaw('TRIM(users.city) as city, UPPER(users.state) as state_code, COUNT(DISTINCT users.id) as total')
                ->groupByRaw('TRIM(users.city), UPPER(users.state)')
                ->orderByDesc('total')
                ->limit(15)
                ->get(),
            'completed_shifts' => CareBooking::query()
                ->join('users', 'users.id', '=', 'care_bookings.caregiver_user_id')
                ->where('users.role', 'caregiver')
                ->whereNotNull('care_bookings.completed_at')
                ->where('care_bookings.completed_at', '>=', $this->start)
                ->whereNotNull('users.city')
                ->where('users.city', '!=', '')
                ->whereNotNull('users.state')
                ->where('users.state', '!=', '')
                ->selectRaw('TRIM(users.city) as city, UPPER(users.state) as state_code, COUNT(care_bookings.id) as total')
                ->groupByRaw('TRIM(users.city), UPPER(users.state)')
                ->orderByDesc('total')
                ->limit(15)
                ->get(),
            default => User::query()
                ->where('role', 'caregiver')
                ->where('created_at', '>=', $this->start)
                ->whereNotNull('city')
                ->where('city', '!=', '')
                ->whereNotNull('state')
                ->where('state', '!=', '')
                ->selectRaw('TRIM(city) as city, UPPER(state) as state_code, COUNT(id) as total')
                ->groupByRaw('TRIM(city), UPPER(state)')
                ->orderByDesc('total')
                ->limit(15)
                ->get(),
        };
    }

    /**
     * @return array{label: string, short_label: string}
     */
    private function metricMeta(): array
    {
        return match ($this->metric) {
            'active' => ['label' => 'Active caregivers by state', 'short_label' => 'Active caregivers'],
            'completed_shifts' => ['label' => 'Completed shifts by caregiver state', 'short_label' => 'Completed shifts'],
            default => ['label' => 'Caregiver signups by state', 'short_label' => 'Signups'],
        };
    }

    /**
     * @return array{provider: string, tile_url: string, tile_attribution: string}
     */
    private function tileMeta(): array
    {
        $maptilerKey = trim((string) config('services.maptiler.key', ''));

        if ($maptilerKey !== '') {
            return [
                'provider' => 'MapTiler',
                'tile_url' => 'https://api.maptiler.com/maps/streets-v2/{z}/{x}/{y}.png?key='.$maptilerKey,
                'tile_attribution' => '&copy; <a href="https://www.maptiler.com/copyright/">MapTiler</a> &copy; OpenStreetMap contributors',
            ];
        }

        return [
            'provider' => 'OpenStreetMap',
            'tile_url' => 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
            'tile_attribution' => '&copy; OpenStreetMap contributors',
        ];
    }

    private function colorForIntensity(float $intensity): string
    {
        return match (true) {
            $intensity >= 0.85 => '#155e75',
            $intensity >= 0.65 => '#0e7490',
            $intensity >= 0.45 => '#0891b2',
            $intensity >= 0.25 => '#06b6d4',
            default => '#67e8f9',
        };
    }
}
