<?php

require __DIR__.'/care-hours-launch-validation-guard.php';
$stagingUri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/');
$stagingPublic = realpath($stagingProject.'/public');
$stagingStatic = realpath($stagingProject.'/public'.$stagingUri);
if ($stagingUri !== '/' && $stagingStatic && is_file($stagingStatic)
    && str_starts_with($stagingStatic, $stagingPublic.DIRECTORY_SEPARATOR)) {
    return false;
}
require_once $stagingProject.'/vendor/autoload.php';
$stagingClock = json_decode(file_get_contents($stagingRoot.'/clock.json'), true, flags: JSON_THROW_ON_ERROR);
if (! preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\+00:00$/', $stagingClock['now'] ?? '')) {
    throw new RuntimeException('A valid explicit staging clock is required.');
}
\Carbon\Carbon::setTestNow($stagingClock['now']);
\Carbon\CarbonImmutable::setTestNow($stagingClock['now']);
define('LARAVEL_START', microtime(true));
$app = require $stagingProject.'/bootstrap/app.php';
$app->afterBootstrapping(\Illuminate\Foundation\Bootstrap\LoadConfiguration::class, $stagingAssertConfiguration);
$app->booted(fn () => \Illuminate\Support\Facades\Event::listen(\Illuminate\Mail\Events\MessageSent::class, $stagingCaptureMail));
$app->handleRequest(\Illuminate\Http\Request::capture());
