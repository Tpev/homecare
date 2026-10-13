<?php

return [
    'required_sources' => ['nc_dac', 'nc_sbi'],
    'max_age_days' => (int) env('SCREENING_MAX_AGE_DAYS', 14),
    'max_candidates' => 1000,
    'max_download_bytes' => 536870912,
    // The official OFNT3CE1.dat is approximately 4.55 GB as of October 2026.
    'max_uncompressed_bytes' => 8589934592,
    'max_record_bytes' => 65536,
    'minimum_retained_ratio' => 0.8,
    'private_directory' => storage_path('app/private/screening'),
    'sources' => [
        'nc_dac' => [
            'page_url' => 'https://webapps.doc.state.nc.us/opi/downloads.do?method=view',
            'allowed_hosts' => ['www.doc.state.nc.us', 'webapps.doc.state.nc.us', 'opus.doc.state.nc.us'],
            'files' => [
                'OFNT3AA1' => ['filename' => 'OFNT3AA1.zip', 'url' => env('SCREENING_DAC_OFNT3AA1_URL', 'https://www.doc.state.nc.us/offenders/OFNT3AA1.zip'), 'type' => 'profile'],
                'OFNT3BB1' => ['filename' => 'OFNT3BB1.zip', 'url' => env('SCREENING_DAC_OFNT3BB1_URL', 'https://www.doc.state.nc.us/offenders/OFNT3BB1.zip'), 'type' => 'commitment'],
                'OFNT3CE1' => ['filename' => 'OFNT3CE1.zip', 'url' => env('SCREENING_DAC_OFNT3CE1_URL', 'https://www.doc.state.nc.us/offenders/OFNT3CE1.zip'), 'type' => 'sentence'],
                'APPT7AA1' => ['filename' => 'APPT7AA1.zip', 'url' => env('SCREENING_DAC_APPT7AA1_URL', 'https://www.doc.state.nc.us/offenders/APPT7AA1.zip'), 'type' => 'probation'],
            ],
            // AA contains demographics, not names. Index commitment and P&P names against AA IDs.
            'fields' => json_decode(env('SCREENING_DAC_FIELDS', '{}'), true) ?: [
                'OFNT3AA1' => ['offender_id' => 'CMDORNUM', 'date_of_birth' => 'CMCLBRTH'],
                'OFNT3BB1' => ['offender_id' => 'CMDORNUM', 'first_name' => 'CMSCFSTN', 'middle_name' => 'CMSCMIDN', 'last_name' => 'CMSCLSTN'],
                'OFNT3CE1' => ['offender_id' => 'CMDORNUM'],
                'APPT7AA1' => ['offender_id' => 'CDDORNUM', 'first_name' => 'CDSFSTNM', 'middle_name' => 'CDSMIDNM', 'last_name' => 'CDSLSTNM', 'date_of_birth' => 'CDCLBRTH'],
            ],
            'descriptor_hashes' => json_decode(env('SCREENING_DAC_DESCRIPTOR_HASHES', '{}'), true) ?: [
                'OFNT3AA1' => '7fe77769b1590a6731d215960e2fae1161e0f6aaa4891b967ead3849745f3310',
                'OFNT3BB1' => '857ccc75e587e7c15436ad8dca7414764ab08fc606392556d7c3b2fe3b94e44e',
                'OFNT3CE1' => 'f0f3c7bf3df7d2749da40021cacba35aece16fdd69bc7fc173557c23289c5453',
                'APPT7AA1' => 'b00252add83de8179f4a0644a3d528bb7058d23325647be6bbd9a072672dd7a0',
            ],
            'minimum_records' => (int) env('SCREENING_DAC_MINIMUM_RECORDS', 10000),
        ],
        'nc_sbi' => [
            'page_url' => 'https://sexoffender.ncsbi.gov/stats.aspx',
            'allowed_hosts' => ['sexoffender.ncsbi.gov'],
            // Optional verified direct HTTPS endpoint; otherwise use the official page postback.
            'files' => ['public' => ['filename' => 'publicsor.zip', 'url' => env('SCREENING_SBI_DOWNLOAD_URL'), 'type' => 'profile']],
            // The verified official public export is nine fixed-width SQL-style text tables.
            // An explicitly reviewed csv/xml layout may override the adapter if format changes.
            'layout' => json_decode(env('SCREENING_SBI_LAYOUT', '{}'), true) ?: ['format' => 'sbi_public_fixed_width'],
            'minimum_records' => (int) env('SCREENING_SBI_MINIMUM_RECORDS', 1000),
        ],
    ],
];
