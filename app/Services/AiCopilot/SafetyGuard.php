<?php

namespace App\Services\AiCopilot;

class SafetyGuard
{
    /** @var array<int,string> */
    private array $medicalKeywords = [
        'diagnose',
        'diagnosis',
        'prescription',
        'prescribe',
        'injection',
        'iv',
        'wound care',
        'catheter',
        'blood pressure dosage',
        'administer medicine',
        'nursing procedure',
    ];

    /**
     * @return array<int,string>
     */
    public function flagsForText(string $text): array
    {
        $haystack = mb_strtolower($text);
        $flags = [];
        foreach ($this->medicalKeywords as $keyword) {
            if (str_contains($haystack, $keyword)) {
                $flags[] = 'medical_scope';
                break;
            }
        }

        return $flags;
    }

    public function safetyHint(): string
    {
        return 'HomeCare supports non-medical care only. For medical procedures, contact licensed clinical providers.';
    }
}

