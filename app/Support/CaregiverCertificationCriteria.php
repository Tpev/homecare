<?php

namespace App\Support;

use App\Models\CaregiverCertificationType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final readonly class CaregiverCertificationCriteria
{
    public const VERIFICATION_ANY_CURRENT = 'any_current';

    public const VERIFICATION_VERIFIED_ONLY = 'verified_only';

    /**
     * @param  array<int, int>  $typeIds
     * @param  array<int, string>  $typeSlugs
     * @param  array<int, string>  $typeLabels
     */
    private function __construct(
        private array $typeIds,
        private array $typeSlugs,
        private array $typeLabels,
        private string $verification,
    ) {}

    public static function empty(): self
    {
        return new self([], [], [], self::VERIFICATION_ANY_CURRENT);
    }

    /**
     * Normalize untrusted Livewire or URL values against the active taxonomy.
     *
     * @param  array<int, mixed>  $values
     */
    public static function fromInput(array $values, ?string $verification = null): self
    {
        $tokens = collect($values)
            ->filter(fn (mixed $value): bool => is_int($value) || is_string($value))
            ->map(fn (int|string $value): string => trim((string) $value))
            ->filter()
            ->unique()
            ->values();

        if ($tokens->isEmpty()) {
            return self::empty();
        }

        $ids = $tokens->filter(fn (string $value): bool => ctype_digit($value))
            ->map(fn (string $value): int => (int) $value)
            ->filter(fn (int $value): bool => $value > 0)
            ->values();
        $slugs = $tokens->reject(fn (string $value): bool => ctype_digit($value))->values();

        $types = CaregiverCertificationType::query()
            ->where('active', true)
            ->where(function (Builder $query) use ($ids, $slugs): void {
                if ($ids->isNotEmpty()) {
                    $query->whereIn('id', $ids->all());
                }

                if ($slugs->isNotEmpty()) {
                    $method = $ids->isNotEmpty() ? 'orWhereIn' : 'whereIn';
                    $query->{$method}('slug', $slugs->all());
                }
            })
            ->orderBy('sort_order')
            ->orderBy('label')
            ->get(['id', 'slug', 'label']);

        if ($types->isEmpty()) {
            return self::empty();
        }

        return new self(
            $types->pluck('id')->map(fn ($id): int => (int) $id)->all(),
            $types->pluck('slug')->map(fn ($slug): string => (string) $slug)->all(),
            $types->pluck('label')->map(fn ($label): string => (string) $label)->all(),
            $verification === self::VERIFICATION_VERIFIED_ONLY
                ? self::VERIFICATION_VERIFIED_ONLY
                : self::VERIFICATION_ANY_CURRENT,
        );
    }

    /**
     * @return array<int, int>
     */
    public function typeIds(): array
    {
        return $this->typeIds;
    }

    /**
     * @return array<int, string>
     */
    public function typeSlugs(): array
    {
        return $this->typeSlugs;
    }

    /**
     * @return array<int, string>
     */
    public function typeLabels(): array
    {
        return $this->typeLabels;
    }

    public function verification(): string
    {
        return $this->verification;
    }

    public function hasSelections(): bool
    {
        return $this->typeIds !== [];
    }

    public function requiresVerification(): bool
    {
        return $this->hasSelections()
            && $this->verification === self::VERIFICATION_VERIFIED_ONLY;
    }

    public function selectedPosition(int $typeId): ?int
    {
        $position = array_search($typeId, $this->typeIds, true);

        return $position === false ? null : $position;
    }

    public function description(): string
    {
        if (! $this->hasSelections()) {
            return 'any certification or training';
        }

        $labels = $this->typeLabels;
        $last = array_pop($labels);
        $description = $labels === [] ? (string) $last : implode(', ', $labels).' and '.$last;

        return $this->requiresVerification()
            ? $description.' with LoLo verification'
            : $description;
    }

    /**
     * @return Collection<int, CaregiverCertificationType>
     */
    public static function activeOptions(): Collection
    {
        return CaregiverCertificationType::query()
            ->where('active', true)
            ->orderBy('sort_order')
            ->orderBy('label')
            ->get(['id', 'slug', 'label', 'sort_order']);
    }
}
