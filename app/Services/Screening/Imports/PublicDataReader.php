<?php

namespace App\Services\Screening\Imports;

use Generator;
use RuntimeException;
use XMLReader;

class PublicDataReader
{
    public function descriptor(string $path, ?string $expectedHash = null): array
    {
        if (filesize($path) > 1048576 || ($expectedHash && ! hash_equals($expectedHash, hash_file('sha256', $path)))) {
            throw new RuntimeException('DAC descriptor changed or exceeds the limit; review the official layout.');
        }
        $fields = [];
        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            if (! preg_match('/^\s*([A-Z][A-Z0-9_]*)\s{2,}(.+?)\s{2,}(CHAR|VARCHAR|DECIMAL|INTEGER|SMALLINT|DATE|TIME|TIMESTAMP)\s+(\d+)\s+(\d+)\s*$/', $line, $matches)) {
                // Only known human-readable column headings/separators are ignored.
                if (preg_match('/^[\s\-=]+$/', $line) || preg_match('/^\s*(FIELD|COLUMN|NAME)\s+.*(POSITION|START|LENGTH)/i', $line)) {
                    continue;
                }
                throw new RuntimeException('Unknown DAC descriptor line; the layout requires review.');
            }
            $start = (int) $matches[4] - 1;
            $length = (int) $matches[5];
            if (isset($fields[$matches[1]]) || $start < 0 || $length < 1 || $start + $length > (int) config('screening.max_record_bytes')) {
                throw new RuntimeException('Invalid or duplicate DAC descriptor field.');
            }
            foreach ($fields as $field) {
                if ($start < $field['start'] + $field['length'] && $start + $length > $field['start']) {
                    throw new RuntimeException('Overlapping DAC descriptor fields.');
                }
            }
            $fields[$matches[1]] = ['start' => $start, 'length' => $length, 'description' => trim($matches[2])];
        }
        if ($fields === []) {
            throw new RuntimeException('No valid DAC descriptor fields found.');
        }

        return $fields;
    }

    public function fixedWidth(string $path, array $schema, string $encoding = 'Windows-1252'): Generator
    {
        $width = max(array_map(fn ($field) => $field['start'] + $field['length'], $schema));
        $stream = fopen($path, 'rb');
        try {
            $number = 0;
            while (($line = fgets($stream, (int) config('screening.max_record_bytes') + 3)) !== false) {
                $number++;
                $line = rtrim($line, "\r\n");
                if (strlen($line) !== $width) {
                    throw new RuntimeException("DAC record {$number} has an unexpected byte width.");
                }
                $row = [];
                foreach ($schema as $key => $field) {
                    $row[$key] = trim(mb_convert_encoding(substr($line, $field['start'], $field['length']), 'UTF-8', $encoding));
                }
                yield $row;
            }
            if (! feof($stream)) {
                throw new RuntimeException('Failed while reading DAC records.');
            }
        } finally {
            fclose($stream);
        }
    }

    public function csv(string $path, array $layout): Generator
    {
        $stream = fopen($path, 'rb');
        $delimiter = $layout['delimiter'] ?? ',';
        if (strlen($delimiter) !== 1) {
            throw new RuntimeException('Invalid configured CSV delimiter.');
        }
        try {
            $headers = null;
            foreach ($this->csvRecords($stream, $delimiter) as $values) {
                if ($headers === null) {
                    $values[0] = preg_replace('/^\xEF\xBB\xBF/', '', $values[0] ?? '');
                    $headers = $values;
                    if ($headers !== ($layout['headers'] ?? []) || count(array_unique($headers)) !== count($headers)) {
                        throw new RuntimeException('SBI CSV headers do not match the verified configured layout.');
                    }

                    continue;
                }
                if (count($values) !== count($headers)) {
                    throw new RuntimeException('SBI CSV record has an unexpected column count.');
                }
                yield array_combine($headers, array_map(fn ($value) => mb_convert_encoding($value, 'UTF-8', $layout['encoding'] ?? 'UTF-8'), $values));
            }
            if (! feof($stream)) {
                throw new RuntimeException('Failed while reading SBI CSV records.');
            }
        } finally {
            fclose($stream);
        }
    }

    private function csvRecords($stream, string $delimiter): Generator
    {
        $record = '';
        $firstLine = true;
        $maximum = (int) config('screening.max_record_bytes');
        while (($line = fgets($stream, $maximum + 2)) !== false) {
            if ($firstLine) {
                $line = preg_replace('/^\xEF\xBB\xBF/', '', $line);
                $firstLine = false;
            }
            $record .= $line;
            if (strlen($record) > $maximum) {
                throw new RuntimeException('SBI CSV record exceeds the size limit.');
            }
            // Validate quote state before str_getcsv, which otherwise accepts unterminated quotes.
            $state = 'start';
            for ($i = 0, $size = strlen($record); $i < $size; $i++) {
                $character = $record[$i];
                if ($state === 'quoted') {
                    if ($character === '"') {
                        if (($record[$i + 1] ?? '') === '"') {
                            $i++;
                        } else {
                            $state = 'closed';
                        }
                    }

                    continue;
                }
                if ($character === $delimiter) {
                    $state = 'start';
                } elseif ($character === "\r" || $character === "\n") {
                    // fgets stops at newline; CR/LF outside quotes must terminate the record.
                    if (trim(substr($record, $i), "\r\n") !== '') {
                        throw new RuntimeException('Malformed SBI CSV record separator.');
                    }
                    break;
                } elseif ($character === '"' && $state === 'start') {
                    $state = 'quoted';
                } elseif ($character === '"' || $state === 'closed') {
                    throw new RuntimeException('Malformed SBI CSV quoting.');
                } else {
                    $state = 'unquoted';
                }
            }
            if ($state !== 'quoted') {
                yield str_getcsv(rtrim($record, "\r\n"), $delimiter, '"', '');
                $record = '';
            }
        }
        if ($record !== '') {
            throw new RuntimeException('Unterminated SBI CSV quoted field.');
        }
    }

    public function xml(string $path, array $layout): Generator
    {
        $reader = new XMLReader;
        $previousErrors = libxml_use_internal_errors(true);
        try {
            if (! $reader->open($path, null, LIBXML_NONET | LIBXML_COMPACT)) {
                throw new RuntimeException('Unable to open SBI XML.');
            }
            $reader->setParserProperty(XMLReader::SUBST_ENTITIES, false);
            $reader->setParserProperty(XMLReader::LOADDTD, false);
            while ($reader->read()) {
                if ($reader->nodeType === XMLReader::DOC_TYPE || $reader->nodeType === XMLReader::ENTITY_REF) {
                    throw new RuntimeException('DTD/entity declarations are not supported in SBI XML.');
                }
                if ($reader->nodeType !== XMLReader::ELEMENT || $reader->localName !== ($layout['record_element'] ?? null)) {
                    continue;
                }
                $xml = $reader->readOuterXml();
                if (strlen($xml) > (int) config('screening.max_record_bytes')) {
                    throw new RuntimeException('SBI XML record exceeds the size limit.');
                }
                $element = simplexml_load_string($xml, \SimpleXMLElement::class, LIBXML_NONET);
                if ($element === false) {
                    throw new RuntimeException('Malformed SBI XML record.');
                }
                $row = [];
                foreach ($element->children() as $key => $value) {
                    if (isset($row[$key]) || $value->count() > 0) {
                        throw new RuntimeException('Repeated or nested SBI XML fields require a verified layout adapter.');
                    }
                    $row[$key] = (string) $value;
                }
                $expected = $layout['headers'] ?? [];
                $keys = array_keys($row);
                sort($expected);
                sort($keys);
                if ($keys !== $expected) {
                    throw new RuntimeException('SBI XML fields do not match the verified configured layout.');
                }
                yield $row;
            }
            if (libxml_get_errors()) {
                throw new RuntimeException('Malformed SBI XML document.');
            }
        } finally {
            $reader->close();
            libxml_clear_errors();
            libxml_use_internal_errors($previousErrors);
        }
    }
}
