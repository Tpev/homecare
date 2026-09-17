param([switch]$SetupOnly)

$ErrorActionPreference = 'Stop'
$workspace = (Resolve-Path (Join-Path $PSScriptRoot '../../..')).Path
Set-Location -LiteralPath $workspace
$previewDatabase = Join-Path $workspace 'storage/app/onboarding-preview.sqlite'
$previewViews = Join-Path $workspace 'storage/framework/onboarding-preview-views'
New-Item -ItemType Directory -Path $previewViews -Force | Out-Null
if (-not (Test-Path -LiteralPath $previewDatabase)) {
    New-Item -ItemType File -Path $previewDatabase | Out-Null
}

$env:APP_ENV = 'local'
$env:APP_URL = 'http://127.0.0.1:8033'
$env:DB_CONNECTION = 'sqlite'
$env:DB_DATABASE = $previewDatabase
$env:VIEW_COMPILED_PATH = $previewViews
$env:CACHE_STORE = 'array'
$env:SESSION_DRIVER = 'file'
$env:SESSION_COOKIE = 'lolo_onboarding_preview'
$env:MAIL_MAILER = 'array'
$env:QUEUE_CONNECTION = 'database'
$env:STRIPE_BYPASS = 'true'
$env:MARKETPLACE_OPS_ALERT_RECIPIENTS = 'onboarding-ops@example.test'

php (Join-Path $PSScriptRoot 'setup-local.php')
if ($LASTEXITCODE -ne 0) { throw 'Local onboarding setup failed.' }
if (-not $SetupOnly) {
    php artisan serve --host=127.0.0.1 --port=8033 --no-reload
}
