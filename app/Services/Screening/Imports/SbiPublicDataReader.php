<?php

namespace App\Services\Screening\Imports;

use Generator;
use RuntimeException;

/** Layout verified against the official publicsor.zip supplied on 2026-10-13. */
class SbiPublicDataReader
{
    public const TABLES = [
        'Public Individual Information.txt' => ['type' => 'profile', 'columns' => ['SexRegistrationNumber' => 21, 'FullName' => 30, 'Race' => 4, 'Sex' => 3, 'Height' => 6, 'Weight' => 6, 'EyeColor' => 8, 'HairColor' => 9, 'DepartmentofCorrectionNumber' => 28, 'RegistrationType' => 16, 'PublicRegistrationTypeDescription' => 50, 'PendingSourceCode' => 17, 'PendingSourceCodeDescription' => 40, 'RegistrationDate' => 20, 'AddressDate' => 20, 'VerifyDate' => 20, 'AddressLine1' => 40, 'AddressLine2' => 40, 'City' => 24, 'State' => 5, 'Zip' => 9, 'CountyName' => 12, 'PrimaryBirthDate' => 20]],
        'Public Name Information.txt' => ['type' => 'alias', 'columns' => ['SexRegistrationNumber' => 21, 'FullName' => 30]],
        'Public Conviction Name Information.txt' => ['type' => 'conviction_name', 'columns' => ['SexRegistrationNumber' => 21, 'OffenseKey' => 10, 'FullName' => 30, 'Nametype' => 8]],
        'Public BirthDate Information.txt' => ['type' => 'birth_date', 'columns' => ['SexRegistrationNumber' => 21, 'BirthDate' => 20]],
        'Public Offense Information.txt' => ['type' => 'offense', 'columns' => ['SexRegistrationNumber' => 21, 'RegistrationDate' => 20, 'ReleaseDate' => 20, 'ConvictionDate' => 20, 'NCGeneralStatute' => 20, 'NCGeneralStatuteDescription' => 60, 'ConfinementSentence' => 19, 'ProbationSentence' => 17, 'CountyName' => 12, 'AOCCourtCountyIdentifier' => 24, 'ConvictionCountyName' => 20, 'ConvictionState' => 15, 'OffenseQualifierDescription' => 27, 'AggravatedOffenseDescription' => 33, 'OffenseDate' => 20, 'VictimAge' => 9, 'OffenseKey' => 10]],
        'Public Address Information.txt' => ['type' => 'address', 'columns' => ['SexRegistrationNumber' => 21, 'AddressDate' => 20, 'VerifyDate' => 20, 'City' => 24, 'State' => 5, 'Zip' => 9, 'CountyName' => 12]],
        'Public NonResident Information.txt' => ['type' => 'nonresident', 'columns' => ['SexRegistrationNumber' => 21, 'NonResidentInStateAddressLine1' => 40, 'NonResidentInStateAddressLine2' => 40, 'NonResidentInStateCity' => 24, 'NonResidentInStateState' => 23, 'NonResidentInStateZip' => 21, 'NonResidentOutofStateAddressLine1' => 40, 'NonResidentOutofStateAddressLine2' => 40, 'NonResidentOutofStateCity' => 25, 'NonResidentOutofStateState' => 26, 'NonResidentOutofStateZip' => 24, 'SchoolBusinessName' => 36]],
        'Public ScarMarkTattoo Information.txt' => ['type' => 'physical_mark', 'columns' => ['SexRegistrationNumber' => 21, 'NCICScarMarkTattoo' => 18, 'ScarMarkTattooText' => 40, 'ScarMarkTattooDescription' => 200]],
        'Public Violation Information.txt' => ['type' => 'violation', 'columns' => ['SexRegistrationNumber' => 21, 'ViolationType' => 13, 'ViolationDescription' => 45]],
    ];

    public function rows(string $path, string $member): Generator
    {
        $columns = self::TABLES[$member]['columns'] ?? throw new RuntimeException('Unknown SBI public table.');
        $width = array_sum($columns) + count($columns) - 1;
        $stream = fopen($path, 'rb');
        try {
            $header = rtrim(fgets($stream) ?: '', "\r\n");
            $ruler = rtrim(fgets($stream) ?: '', "\r\n");
            $expectedRuler = implode(' ', array_map(fn ($length) => str_repeat('-', $length), $columns));
            if ($ruler !== $expectedRuler || strlen($header) !== $width) {
                throw new RuntimeException("SBI {$member} column widths changed; review the official layout.");
            }
            $offset = 0;
            foreach ($columns as $column => $length) {
                if (trim(substr($header, $offset, $length)) !== $column) {
                    throw new RuntimeException("SBI {$member} column names changed; review the official layout.");
                }
                $offset += $length + 1;
            }
            $number = 0;
            while (($line = fgets($stream, $width + 4)) !== false) {
                $number++;
                $line = rtrim($line, "\r\n");
                if (strlen($line) !== $width) {
                    throw new RuntimeException("SBI {$member} record {$number} has an unexpected width.");
                }
                $row = [];
                $offset = 0;
                foreach ($columns as $column => $length) {
                    $row[$column] = trim(mb_convert_encoding(substr($line, $offset, $length), 'UTF-8', 'Windows-1252'));
                    if ($offset + $length < $width && $line[$offset + $length] !== ' ') {
                        throw new RuntimeException("SBI {$member} record {$number} has an invalid column separator.");
                    }
                    $offset += $length + 1;
                }
                yield $row;
            }
            if (! feof($stream)) {
                throw new RuntimeException('Failed while reading SBI public records.');
            }
        } finally {
            fclose($stream);
        }
    }

    public function fields(string $type): array
    {
        $fields = ['offender_id' => 'SexRegistrationNumber'];
        if (in_array($type, ['profile', 'alias', 'conviction_name'], true)) {
            $fields += ['full_name' => 'FullName', 'name_order' => 'last_comma_first'];
        }
        if ($type === 'profile') {
            $fields['date_of_birth'] = 'PrimaryBirthDate';
        }

        return $fields;
    }
}
