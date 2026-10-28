<?php

require __DIR__.'/care-hours-gift-onboarding-guard.php';
$stagingUri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/');
$stagingPublic = realpath($stagingProject.'/public');
$stagingStatic = realpath($stagingProject.'/public'.$stagingUri);
if ($stagingUri !== '/' && $stagingStatic && is_file($stagingStatic)
    && str_starts_with($stagingStatic, $stagingPublic.DIRECTORY_SEPARATOR)) {
    return false;
}
require_once $stagingProject.'/vendor/autoload.php';
define('LARAVEL_START', microtime(true));
$app = require $stagingProject.'/bootstrap/app.php';
$app->useStoragePath($stagingRoot.'/storage');
$app->afterBootstrapping(\Illuminate\Foundation\Bootstrap\LoadConfiguration::class, $stagingAssertConfiguration);
$app->booted(fn () => \Illuminate\Support\Facades\Event::listen(\Illuminate\Mail\Events\MessageSent::class, $stagingCaptureMail));
$app->handleRequest(\Illuminate\Http\Request::capture());
