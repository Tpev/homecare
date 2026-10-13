param([int]$Port = 8020, [switch]$PrepareOnly)
$ErrorActionPreference = 'Stop'
$projectDirectory = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
Set-Location -LiteralPath $projectDirectory
$previewDatabase = Join-Path $projectDirectory 'database\shared-care-local.sqlite'
if (-not (Test-Path -LiteralPath $previewDatabase)) { New-Item -Path $previewDatabase -ItemType File | Out-Null }
$env:APP_ENV = 'local'
$env:APP_CONFIG_CACHE = Join-Path $projectDirectory 'storage\framework\cache\shared-care-preview-config.php'
$env:APP_ROUTES_CACHE = Join-Path $projectDirectory 'storage\framework\cache\shared-care-preview-routes.php'
if (Test-Path -LiteralPath $env:APP_CONFIG_CACHE) { throw 'Remove the isolated preview config cache before starting; do not use cached database credentials.' }
$env:APP_URL = "http://127.0.0.1:$Port"
$env:DB_CONNECTION = 'sqlite'
$env:DB_DATABASE = $previewDatabase
$env:CACHE_STORE = 'array'
$env:SESSION_DRIVER = 'file'
$env:SESSION_COOKIE = 'shared_care_local_session'
$env:QUEUE_CONNECTION = 'sync'
$env:MAIL_MAILER = 'array'
$env:STRIPE_BYPASS = 'true'
$env:DIDIT_BYPASS = 'true'
$env:TWILIO_BYPASS = 'true'
$env:MARKETPLACE_CAREGIVER_PRELAUNCH_MODE = 'false'
$env:SLACK_OPS_NOTIFICATIONS_ENABLED = 'false'
$env:AI_SUPPORT_PROVIDER_ENABLED = 'false'
php artisan migrate --force
if ($LASTEXITCODE -ne 0) { throw 'Local preview migration failed.' }
php artisan homecare:shared-care-demo
if ($LASTEXITCODE -ne 0) { throw 'Local preview demo setup failed.' }
if (-not $PrepareOnly) { php artisan serve --host=127.0.0.1 --port=$Port }
