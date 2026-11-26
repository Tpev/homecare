<?php

// Static contract only: no Laravel bootstrap, credentials, connections or business data.
require __DIR__.'/../../app/Services/Reporting/ReportingCatalog.php';

echo json_encode([
    'version' => \App\Services\Reporting\ReportingCatalog::VERSION,
    'datasets' => \App\Services\Reporting\ReportingCatalog::datasets(),
    'json_keys' => \App\Services\Reporting\ReportingCatalog::businessJsonKeys(),
], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);
