#!/usr/bin/env bash
# Execute the installer's exact transforms against temporary fixtures, never the installer.
set -Eeuo pipefail
PROJECT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/../.." && pwd)"
PYTHON_BIN="${PYTHON_BIN:-python3}"
PHP_BIN="${HOMECARE_SETUP_TEST_PHP:-php}"
"$PYTHON_BIN" - "$PROJECT_DIR" "$PHP_BIN" "$BASH" <<'PY'
import copy, json, os, pathlib, re, subprocess, sys, tempfile

project, php, bash = pathlib.Path(sys.argv[1]), sys.argv[2], sys.argv[3]
installer = (project / 'docs/deployment/screening-supervisor-setup.sh').read_text()

def block(prefix, tag='PY'):
    start = installer.index(prefix)
    start = installer.index("<<'" + tag + "'\n", start) + len(tag) + 5
    end = installer.index('\n' + tag + '\n', start)
    return installer[start:end]

cron_remove = block('python3 - "$BACKUP/root.crontab"')
cron_add = block('python3 - "$BACKUP/root.paused.crontab"')
env_update = block('python3 - "$SHARED_ENV"')
supervisor_update = block('python3 - "$SUPERVISOR_CONFIG" "$BACKUP/supervisor.wrapped.conf"')
cache_update = block('sudo -u www-data /usr/bin/php /dev/stdin "$CACHE"', 'PHP')
checks = 0

def run(source, args, success=True, prelude=''):
    result = subprocess.run([sys.executable, '-', *map(str, args)], input=prelude + source,
                            text=True, capture_output=True)
    assert (result.returncode == 0) == success, 'Unexpected transform outcome: ' + result.stderr
    assert 'fixture-secret' not in result.stdout + result.stderr, 'Transform printed a secret.'
    return result

with tempfile.TemporaryDirectory(prefix='homecare-setup-fixture-') as directory:
    root = pathlib.Path(directory)
    original, target = root / 'root.cron', root / 'result.cron'
    old = '* * * * * cd /var/www/homecare && php artisan schedule:run >> /dev/null 2>&1'
    new = '* * * * * /usr/local/libexec/homecare-runtime-entrypoint scheduler /var/www/homecare >> /var/log/homecare-scheduler.log 2>&1'
    untouched = b'# keep this comment\nMAILTO=fixture-secret@example.invalid\n17 4 * * * /root/backup\n'
    original.write_bytes(untouched + old.encode() + b'\n')
    run(cron_remove, [original, target, old])
    assert target.read_bytes() == untouched
    checks += 1
    for content in (untouched, untouched + (old + '\n' + old + '\n').encode()):
        original.write_bytes(content)
        run(cron_remove, [original, target, old], success=False)
        assert target.read_bytes() == untouched
        checks += 1
    paused = root / 'paused.cron'
    paused.write_bytes(untouched)
    original.write_bytes(untouched + old.encode() + b'\n')
    mocked_cron = 'import subprocess\nsubprocess.check_output = lambda *a, **k: ' + repr(untouched) + '\n'
    run(cron_add, [paused, original, target, old, new], prelude=mocked_cron)
    assert target.read_bytes() == untouched + new.encode() + b'\n'
    checks += 1
    paused.write_bytes(untouched + b'# concurrent administrator edit\n')
    run(cron_add, [paused, original, target, old, new], success=False, prelude=mocked_cron)
    assert target.read_bytes() == untouched + new.encode() + b'\n'
    checks += 1
    scope_before = b'SHELL=/bin/bash\nPATH=/usr/bin:/bin\n'
    scope_after = b'SHELL=/bin/false\n17 4 * * * /root/backup\n'
    original.write_bytes(scope_before + old.encode() + b'\n' + scope_after)
    paused.write_bytes(scope_before + scope_after)
    mocked_cron = 'import subprocess\nsubprocess.check_output = lambda *a, **k: ' + repr(paused.read_bytes()) + '\n'
    run(cron_add, [paused, original, target, old, new], prelude=mocked_cron)
    assert target.read_bytes() == scope_before + new.encode() + b'\n' + scope_after
    checks += 1

    environment = root / '.env'
    secret_lines = b'APP_KEY=fixture-secret\r\nVIEW_COMPILED_PATH=/immutable/views\r\n# retain me\r\n'
    # Windows has no uid/gid chown API; fixture only suppresses that metadata operation.
    platform_prelude = 'import os\nos.chown = lambda *a, **k: None\n' if os.name == 'nt' else ''
    environment.write_bytes(secret_lines + b'DB_QUEUE_RETRY_AFTER=90\r\n')
    run(env_update, [environment], prelude=platform_prelude)
    assert environment.read_bytes() == secret_lines + b'DB_QUEUE_RETRY_AFTER=180\r\n'
    checks += 1
    environment.write_bytes(secret_lines)
    run(env_update, [environment], prelude=platform_prelude)
    assert environment.read_bytes() == secret_lines + b'DB_QUEUE_RETRY_AFTER=180\n'
    checks += 1
    ambiguous = secret_lines + b'DB_QUEUE_RETRY_AFTER=90\nexport DB_QUEUE_RETRY_AFTER=91\n'
    environment.write_bytes(ambiguous)
    run(env_update, [environment], success=False, prelude=platform_prelude)
    assert environment.read_bytes() == ambiguous
    checks += 1

    old_command = '/usr/bin/php /var/www/homecare/artisan queue:work database --queue=default --sleep=1 --tries=3 --timeout=120 --max-time=3600'
    new_command = '/usr/local/libexec/homecare-runtime-entrypoint worker /var/www/homecare'
    supervisor = root / 'supervisor.conf'
    supervisor_target = root / 'wrapped.conf'
    settings = '[program:homecare-worker]\ncommand=' + old_command + '\nuser=www-data\nstdout_logfile=/var/log/homecare-worker.log\n'
    supervisor.write_text(settings)
    run(supervisor_update, [supervisor, supervisor_target, old_command, new_command])
    assert supervisor_target.read_text() == settings.replace(old_command, new_command)
    checks += 1
    supervisor.write_text(settings.replace(old_command, old_command + ' --unknown-setting'))
    run(supervisor_update, [supervisor, supervisor_target, old_command, new_command], success=False)
    checks += 1

    fixture = root / 'config.php'
    candidate = root / 'config-next.php'
    transformer = root / 'transform.php'
    transformer.write_text(cache_update)
    expected = {'queue': {'default': 'database', 'connections': {'database': {'driver': 'database', 'queue': 'default', 'retry_after': 90}}},
                'view': {'compiled': '/release/specific/views'}, 'app': {'key': 'fixture-secret'},
                'unrelated': {'boolean': True, 'nested': [1, 2, None]}}
    def write_config(value):
        encoded = json.dumps(json.dumps(value))
        fixture.write_text('<?php return json_decode(' + encoded + ', true);')
    write_config(expected)
    result = subprocess.run([php, str(transformer), str(fixture), str(candidate)], text=True, capture_output=True)
    assert result.returncode == 0, result.stderr
    assert 'fixture-secret' not in result.stdout + result.stderr
    actual = subprocess.check_output([php, '-r', 'echo json_encode(require $argv[1]);', str(candidate)], text=True)
    desired = copy.deepcopy(expected)
    desired['queue']['connections']['database']['retry_after'] = 180
    assert json.loads(actual) == desired
    checks += 1
    # Existing candidate files cannot be overwritten, and unexpected runtime changes fail closed.
    result = subprocess.run([php, str(transformer), str(fixture), str(candidate)], text=True, capture_output=True)
    assert result.returncode != 0
    checks += 1
    candidate.unlink()
    expected['queue']['connections']['database']['retry_after'] = 30
    write_config(expected)
    result = subprocess.run([php, str(transformer), str(fixture), str(candidate)], text=True, capture_output=True)
    assert result.returncode != 0 and not candidate.exists()
    checks += 1

# Structural regression checks for critical ordering, without invoking production paths.
assert installer.index('flock -n 8') < installer.index('verify_original_configuration\n\nBACKUP=')
assert 'exec 8</tmp/homecare-deploy.lock' in installer
assert 'exec 8>/tmp/homecare-deploy.lock' not in installer
assert "sudo -u ubuntu /bin/bash -c 'umask 022; set -o noclobber; : > /tmp/homecare-deploy.lock'" in installer
assert '$(stat -Lc \'%d:%i\' -- /proc/self/fd/8)' in installer
assert '8#$deploy_lock_mode & 002' in installer
assert installer.index('crontab -u root "$BACKUP/root.paused.crontab"') < installer.index('supervisorctl stop "$PROCESS"')
assert installer.index('supervisorctl stop "$PROCESS"') < installer.index('wait_for_legacy_tasks "$cron_reload_wait"')
assert '65 - (SECONDS - CRON_REMOVED_AT)' in installer
assert 'p.name == exempt' not in installer
assert installer.index('sudo -u www-data /usr/bin/php "$QUEUE_CHECK" "$CACHE" 120') < installer.index('supervisorctl update homecare-worker')
assert "sudo -u www-data /usr/bin/php -r '\n    $c = require $argv[1];" in installer
assert 'umask(0077);' in cache_update
assert 'systemctl reset-failed "$DUPLICATE_UNIT"' in installer
assert not re.search(r'^\s*(?:sudo\s+)?(?:php|\$PHP_BIN|"\$PHP_BIN")\s+artisan\s', installer, re.M)
assert 'screening:import' not in installer and '--refresh-identities' not in installer
checks += 1
# Keep bootstrap and controller fingerprints byte-for-byte compatible, including
# the actual digest/lock inode calculation rather than just the field labels.
controller = (project / 'docs/deployment/screening-supervisor-runtime-control.sh').read_text()
function = re.compile(r'(?ms)^topology\(\) \{\n.*?^\}')
assert function.search(installer).group() == function.search(controller).group()
assert installer.index('install -m 0644 -o root -g root "$BACKUP/supervisor.wrapped.conf"') < installer.index('topology > "$BACKUP/paused-final"')
assert installer.index('topology > "$BACKUP/paused-final"') < installer.index('supervisorctl update homecare-worker')
assert 'CONFIG="$RUNTIME_CONFIG"' in installer and 'PHP_BIN=/usr/bin/php' in installer
checks += 1
with tempfile.TemporaryDirectory(prefix='homecare-marker-fixture-') as directory:
    root = pathlib.Path(directory)
    environment = os.environ.copy()
    environment.update(APP_DIR='/var/www/homecare', STATE_DIR=root.as_posix(),
                       PHP_BIN='/usr/bin/php', PROCESS='homecare-worker:homecare-worker_00')
    for key in ('CONFIG', 'SUPERVISOR_CONFIG', 'ENTRYPOINT', 'QUEUE_CHECK'):
        path = root / key.lower()
        path.write_text('reviewed-fixture-' + key)
        environment[key] = path.as_posix()
    for name in ('start.lock', 'run.lock'):
        (root / name).write_bytes(b'')
    bootstrap = subprocess.check_output([bash, '-c', function.search(installer).group() + '\ntopology'], env=environment)
    control = subprocess.check_output([bash, '-c', function.search(controller).group() + '\ntopology'], env=environment)
    assert bootstrap == control
    assert len(bootstrap.splitlines()) == 14
    assert b'config_sha256=' in bootstrap and b'start_lock_id=' in bootstrap
    changed = pathlib.Path(environment['CONFIG'])
    changed.write_text('changed-configuration')
    new_marker = subprocess.check_output([bash, '-c', function.search(controller).group() + '\ntopology'], env=environment)
    assert new_marker != bootstrap
checks += 1
print(f'Supervisor setup transforms passed ({checks} checks; no services or application database accessed).')
PY
