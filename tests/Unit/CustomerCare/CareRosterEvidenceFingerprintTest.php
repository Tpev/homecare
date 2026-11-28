<?php

namespace Tests\Unit\CustomerCare;

use App\Services\CustomerCare\CareRosterEvidence;
use PHPUnit\Framework\TestCase;

class CareRosterEvidenceFingerprintTest extends TestCase
{
    public function test_json_object_order_does_not_change_approval_evidence_but_values_and_lists_do(): void
    {
        $evidence = new CareRosterEvidence;
        $original = ['sms_allowed' => true, 'invitation_id' => 7,
            'evidence_version' => ['sms' => 11, 'availability' => '2026-11-28 10:00:00'],
            'conflicts' => ['care_bookings:3', 'care_bookings:9']];
        $stored = ['conflicts' => ['care_bookings:3', 'care_bookings:9'],
            'evidence_version' => ['availability' => '2026-11-28 10:00:00', 'sms' => 11],
            'invitation_id' => 7, 'sms_allowed' => true];
        $fingerprint = $evidence->stateFingerprint($original);
        $this->assertSame($fingerprint, $evidence->stateFingerprint($stored));
        $this->assertNotSame($fingerprint, $evidence->stateFingerprint(array_replace($stored, ['sms_allowed' => false])));
        $changedReply = $stored;
        $changedReply['evidence_version']['sms'] = 12;
        $this->assertNotSame($fingerprint, $evidence->stateFingerprint($changedReply));
        $this->assertNotSame($fingerprint, $evidence->stateFingerprint(array_replace($stored, ['conflicts' => array_reverse($stored['conflicts'])])));
    }
}
