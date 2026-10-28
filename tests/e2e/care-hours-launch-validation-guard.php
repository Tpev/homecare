<?php

// This guard runs before Laravel can read configuration or touch a database.
$stagingProject = dirname(__DIR__, 2);
$stagingRoot = $stagingProject.'/tmp/care-hours-launch-validation';
$stagingExpectedDatabase = str_replace('\\', '/', $stagingRoot.'/care-hours-launch-validation.sqlite');
$stagingActualDatabase = str_replace('\\', '/', (string) getenv('DB_DATABASE'));
$stagingMode = (string) getenv('CARE_HOURS_LAUNCH_VALIDATION_PAYMENT_MODE');
$stagingSecret = (string) getenv('STRIPE_SECRET');
if (getenv('APP_ENV') !== 'playwright'
    || getenv('CARE_HOURS_LAUNCH_VALIDATION_MARKER') !== 'isolated-launch-validation'
    || $stagingActualDatabase !== $stagingExpectedDatabase
    || getenv('DB_CONNECTION') !== 'sqlite' || getenv('DB_URL') !== ''
    || getenv('APP_CONFIG_CACHE') !== 'tmp/care-hours-launch-validation/config.php'
    || file_exists($stagingRoot.'/config.php')
    || str_replace('\\', '/', (string) getenv('LARAVEL_STORAGE_PATH')) !== str_replace('\\', '/', $stagingRoot.'/storage')
    || getenv('MAIL_MAILER') !== 'array'
    || ! in_array($stagingMode, ['stripe_test', 'bypass'], true)
    || ($stagingMode === 'stripe_test' && (! preg_match('/^(sk|rk|rkcs)_test_/', $stagingSecret) || getenv('STRIPE_BYPASS') !== 'false'))
    || ($stagingMode === 'bypass' && ($stagingSecret !== '' || getenv('STRIPE_BYPASS') !== 'true'))) {
    throw new RuntimeException('This journey requires its isolated staging database, storage, and explicitly configured test payment mode.');
}

// Runs immediately after configuration loads, before providers can open a connection.
$stagingAssertConfiguration = function ($app) use ($stagingExpectedDatabase): void {
    $config = $app->make('config');
    if ($config->get('database.default') !== 'sqlite'
        || str_replace('\\', '/', (string) $config->get('database.connections.sqlite.database')) !== $stagingExpectedDatabase
        || ! empty($config->get('database.connections.sqlite.url'))
        || $config->get('mail.default') !== 'array') {
        throw new RuntimeException('Resolved staging configuration is not isolated.');
    }
};

$stagingCaptureMail = function ($event) use ($stagingRoot): void {
    $message = $event->message;
    $id = bin2hex(random_bytes(8));
    $recipients = array_map(fn ($address) => $address->getAddress(), $message->getTo());
    file_put_contents($stagingRoot.'/mail/'.$id.'.html', $message->getHtmlBody() ?: '<pre>'.htmlspecialchars($message->getTextBody() ?? '').'</pre>');
    file_put_contents($stagingRoot.'/mail/index.jsonl', json_encode([
        'id' => $id, 'to' => $recipients, 'subject' => $message->getSubject(), 'application_time' => now()->toIso8601String(),
        'transport' => 'array', 'delivered_externally' => false,
    ], JSON_UNESCAPED_SLASHES)."\n", FILE_APPEND | LOCK_EX);
};
