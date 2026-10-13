#!/usr/bin/env bash
# Exercise the installer's exact recovery guards with isolated filesystem/service fixtures.
# No production installer, service, database, network, or privileged command is invoked.
set -Eeuo pipefail
PROJECT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/../.." && pwd)"
PYTHON_BIN="${PYTHON_BIN:-python3}"
"$PYTHON_BIN" - "$PROJECT_DIR" "$BASH" <<'PY'
import hashlib, json, os, pathlib, re, subprocess, sys, tempfile

project, bash = pathlib.Path(sys.argv[1]), sys.argv[2]
installer = (project / 'docs/deployment/screening-supervisor-setup.sh').read_text()
checks = 0

def block(prefix):
    start = installer.index("<<'PY'\n", installer.index(prefix)) + len("<<'PY'\n")
    return installer[start:installer.index('\nPY\n', start)]

def function(name):
    return re.search(r'(?ms)^' + re.escape(name) + r'\(\) \{\n.*?^\}', installer).group()

checkpoint = block('verify_resume_checkpoint()')
waiter = block('wait_for_legacy_tasks()')
old = '* * * * * cd /var/www/homecare && php artisan schedule:run >> /dev/null 2>&1'
release = '/var/www/homecare-deploy/releases/fixture-original'
helpers = ('screening-runtime-entrypoint.sh', 'screening-supervisor-runtime-control.sh', 'check-screening-queue.php')

with tempfile.TemporaryDirectory(prefix='homecare-resume-fixture-') as directory:
    base = pathlib.Path(directory).resolve()
    backup_root = base / 'backups'
    backup = backup_root / '20261013T165702Z-1659597'
    bundle, live = base / 'bundle', base / 'live'
    for path in (backup, bundle, live):
        path.mkdir(parents=True)
    untouched = b'# retain this comment\nMAILTO=fixture-secret@example.invalid\n17 4 * * * /root/backup\n'
    original = untouched + old.encode() + b'\nSHELL=/bin/false\n'
    paused = untouched + b'SHELL=/bin/false\n'
    properties = b'FragmentPath=/usr/lib/systemd/system/php8.3-fpm.service\nDropInPaths=\nKillSignal=15\nKillMode=control-group\nSendSIGKILL=yes\nTimeoutStopUSec=1min 30s\n'
    account = b'uid=1000(ubuntu) gid=1000(ubuntu) groups=1000(ubuntu)\n'
    for name in ('supervisor.conf', 'shared.env', 'config.php'):
        value = b'APP_KEY=fixture-secret\r\nDB_QUEUE_RETRY_AFTER=90\r\n' if name == 'shared.env' else ('fixture-secret-' + name + '\n').encode()
        (backup / name).write_bytes(value)
        (live / name).write_bytes(value)
    saved = {'root.crontab': original, 'root.paused.crontab': paused,
             'fpm-properties': properties, 'duplicate-worker-enabled': b'enabled\n',
             'deploy-account': account, 'active-release': (release + '\n').encode()}
    for name, value in saved.items():
        (backup / name).write_bytes(value)
    inventory = ''
    for name in helpers:
        value = ('reviewed-helper-' + name + '\n').encode()
        (bundle / name).write_bytes(value)
        inventory += hashlib.sha256(value).hexdigest() + '  /old/git-stage/' + name + '\n'
    (backup / 'bundle.sha256').write_text(inventory)

    # Linux ownership/mode and service reads are explicit fixture boundaries. Actual
    # file bytes, canonical paths, digest parsing and validation code are unchanged.
    mock = '''
import os, pathlib, stat, subprocess
_fixture_root = pathlib.Path(FIXTURE['base'])
_stat = pathlib.Path.stat
def fixture_stat(path, *a, **k):
    result = _stat(path, *a, **k)
    if path == _fixture_root or _fixture_root in path.parents:
        values = list(result)
        values[4] = 0
        values[5] = 0
        permissions = 0o700 if stat.S_ISDIR(result.st_mode) else 0o600
        values[0] = stat.S_IFMT(result.st_mode) | permissions
        override = FIXTURE.get('metadata', {}).get(path.as_posix(), {})
        if 'uid' in override: values[4] = override['uid']
        if 'gid' in override: values[5] = override['gid']
        if 'nlink' in override: values[3] = override['nlink']
        if 'mode' in override: values[0] = override['mode']
        return os.stat_result(values)
    return result
pathlib.Path.stat = fixture_stat
def check_output(command, **kwargs):
    if command == ['crontab', '-u', 'root', '-l']: return FIXTURE['cron'].encode()
    if command[:3] == ['systemctl', 'show', 'php8.3-fpm.service']: return FIXTURE['fpm'].encode()
    if command == ['id', 'ubuntu']: return FIXTURE['account'].encode()
    if command == ['getent', 'group', 'www-data']: return FIXTURE.get('webgroup', 'www-data:x:33:\\n').encode()
    raise AssertionError('Unexpected service command: ' + repr(command))
def run(command, **kwargs):
    assert command == ['systemctl', 'is-enabled', 'homecare-worker.service']
    return subprocess.CompletedProcess(command, FIXTURE.get('enabled_status', 0), FIXTURE['enabled'].encode())
subprocess.check_output, subprocess.run = check_output, run
'''
    defaults = dict(base=base.as_posix(), cron=paused.decode(), fpm=properties.decode(),
                    account=account.decode(), enabled='enabled\n')
    runtime_paths = [live / 'runtime-state', live / 'entrypoint', live / 'control', live / 'queue-check.php', live / 'runtime.conf']
    args = [backup_root, backup, bundle, release, live / 'supervisor.conf', live / 'shared.env',
            live / 'config.php', old, 'php8.3-fpm.service', 'homecare-worker.service', 'preinstall', *runtime_paths]

    def verify(success=True, changed=None, arguments=None, message=None):
        global checks
        settings = dict(defaults, **(changed or {}))
        source = 'FIXTURE = ' + repr(settings) + '\n' + mock + checkpoint
        before = {path.relative_to(base): path.read_bytes() for path in base.rglob('*') if path.is_file()}
        result = subprocess.run([sys.executable, '-', *map(str, arguments or args)], input=source,
                                text=True, capture_output=True)
        after = {path.relative_to(base): path.read_bytes() for path in base.rglob('*') if path.is_file()}
        assert before == after, 'Recovery validation modified the checkpoint or runtime files.'
        assert (result.returncode == 0) == success, result.stdout + result.stderr
        assert 'fixture-secret' not in result.stdout + result.stderr, 'Recovery check disclosed file contents.'
        if message:
            assert message in result.stderr, result.stderr
        checks += 1

    verify()
    verify(False, {'cron': paused.decode() + '# concurrent administrator edit\n'}, message='Live root cron differs')
    for name in ('supervisor.conf', 'shared.env', 'config.php'):
        target = live / name
        content = target.read_bytes()
        target.write_bytes(content + b'changed\n')
        verify(False, message='Current file differs')
        target.write_bytes(content)
    for name, value, error in (
        ('root.crontab', untouched, 'exact original scheduler once'),
        ('root.crontab', original + old.encode() + b'\n', 'exact original scheduler once'),
        ('root.crontab', original + b'* * * * * php artisan schedule:work\n', 'another scheduler'),
        ('root.paused.crontab', paused + b'# corruption\n', 'preserve the original cron'),
        ('active-release', b'/another/release\n', 'application release differs'),
        ('bundle.sha256', inventory.replace('screening-runtime-entrypoint.sh', 'unreviewed-helper.sh').encode(), 'Unexpected or duplicate helper'),
        ('bundle.sha256', inventory.splitlines()[0].encode() + b'\n', 'inventory is incomplete'),
        ('bundle.sha256', (inventory + inventory.splitlines()[0] + '\n').encode(), 'Unexpected or duplicate helper'),
        ('bundle.sha256', b'not-a-checksum\n', 'Malformed original helper'),
    ):
        target = backup / name
        content = target.read_bytes()
        target.write_bytes(value)
        verify(False, message=error)
        target.write_bytes(content)
    alternate = list(args)
    alternate[3] = '/var/www/homecare-deploy/releases/another'
    verify(False, arguments=alternate, message='application release differs')
    verify(False, {'fpm': properties.decode() + 'changed\n'}, message='PHP-FPM properties differ')
    verify(False, {'enabled': 'disabled\n', 'enabled_status': 1}, message='enablement differs')
    verify(False, {'enabled_status': 3}, message='enablement differs')
    verify(False, {'account': account.decode() + 'changed\n'}, message='Deploy account differs')
    for name in helpers:
        target = bundle / name
        content = target.read_bytes()
        target.write_bytes(content + b'changed\n')
        verify(False, message='Runtime helper changed')
        target.write_bytes(content)
    for path, override, error in (
        (backup_root, {'uid': 1000}, 'Backup root must be root-owned'),
        (backup_root, {'mode': 0o040777}, 'Backup root must be root-owned'),
        (backup, {'uid': 1000}, 'Backup directory must be root-owned'),
        (backup, {'mode': 0o040755}, 'Backup directory must be root-owned'),
        (backup, {'mode': 0o120700}, 'Backup directory must be root-owned'),
        (backup / 'shared.env', {'mode': 0o120600}, 'regular backup file'),
        (live / 'config.php', {'mode': 0o120600}, 'checkpoint file must be regular'),
    ):
        verify(False, {'metadata': {path.as_posix(): override}}, message=error)
    alternate = list(args)
    alternate[1] = base / 'outside-backups'
    alternate[1].mkdir()
    verify(False, arguments=alternate, message='canonical direct child')
    missing = backup / 'deploy-account'
    missing.unlink()
    verify(False, message='deploy-account')
    missing.write_bytes(account)

    # The later checkpoint is accepted only when the whole closed bootstrap gate
    # exists, the environment contains the one intended edit, and all remaining
    # live configuration still equals the private pre-setup backup.
    state, entrypoint, control, queue_check, runtime_config = runtime_paths
    state.mkdir()
    metadata = {}
    def installed(path, content, mode, uid=0, gid=0):
        path.write_bytes(content)
        metadata[path.as_posix()] = dict(mode=0o100000 | mode, uid=uid, gid=gid, nlink=1)
    metadata[state.as_posix()] = dict(mode=0o040750, uid=1000, gid=33)
    marker = b'bootstrap incomplete; runtime admission is closed\n'
    for name, mode in (('start.lock', 0o640), ('run.lock', 0o640), ('operation.lock', 0o600)):
        installed(state / name, b'', mode, 1000, 33)
    installed(state / 'paused', marker, 0o640, 1000, 33)
    (backup / 'paused').write_bytes(marker)
    for name, target, mode in zip(helpers, (entrypoint, control, queue_check), (0o755, 0o755, 0o644)):
        installed(target, (bundle / name).read_bytes(), mode)
    config_start = installer.index("<<'EOF'\n", installer.index('cat > "$BACKUP/runtime.conf"')) + len("<<'EOF'\n")
    config_bytes = installer[config_start:installer.index('\nEOF\n', config_start)].encode() + b'\n'
    (backup / 'runtime.conf').write_bytes(config_bytes)
    installed(runtime_config, config_bytes, 0o640, 0, 33)
    (live / 'shared.env').write_bytes((backup / 'shared.env').read_bytes().replace(b'RETRY_AFTER=90', b'RETRY_AFTER=180'))
    bootstrap_args = list(args)
    bootstrap_args[10] = 'bootstrap'
    bootstrap_settings = dict(account='uid=1000(ubuntu) gid=1000(ubuntu) groups=33(www-data),1000(ubuntu)\n', metadata=metadata)

    def verify_bootstrap(success=True, changed=None, message=None):
        settings = dict(bootstrap_settings, **(changed or {}))
        return verify(success, settings, bootstrap_args, message)

    lock_ids = {path.name: path.stat().st_ino for path in state.iterdir()}
    verify_bootstrap()
    verify_bootstrap()
    assert {path.name: path.stat().st_ino for path in state.iterdir()} == lock_ids
    checks += 1
    verify_bootstrap(False, {'cron': original.decode()}, 'Live root cron differs')
    verify_bootstrap(False, {'fpm': properties.decode() + 'DropInPaths=/changed\n'}, 'PHP-FPM properties differ')
    verify_bootstrap(False, {'enabled': 'disabled\n', 'enabled_status': 1}, 'enablement differs')
    for changed_account in (
        account.decode(),
        'uid=1001(ubuntu) gid=1000(ubuntu) groups=1000(ubuntu),33(www-data)\n',
        'uid=1000(ubuntu) gid=1001(ubuntu) groups=1000(ubuntu),33(www-data)\n',
        'uid=1000(ubuntu) gid=1000(ubuntu) groups=1000(ubuntu),33(www-data),99(unexpected)\n',
        'uid=1000(ubuntu) gid=1000(ubuntu) groups=33(www-data)\n',
        'uid=1000(ubuntu) gid=1000(ubuntu) groups=1000(ubuntu),33(www-data),33(www-data)\n',
    ):
        verify_bootstrap(False, {'account': changed_account}, 'Deploy account')
    verify_bootstrap(False, {'webgroup': 'other:x:33:\n'}, 'www-data group')
    for target, error in (
        (live / 'supervisor.conf', 'Current file differs'),
        (live / 'shared.env', 'Current file differs'),
        (live / 'config.php', 'Current file differs'),
        (entrypoint, 'Installed runtime helper differs'),
        (control, 'Installed runtime helper differs'),
        (queue_check, 'Installed runtime helper differs'),
        (runtime_config, 'runtime configuration differs'),
        (backup / 'runtime.conf', 'runtime configuration differs'),
        (state / 'paused', 'admission marker differs'),
        (backup / 'paused', 'admission marker differs'),
        (state / 'start.lock', 'lock contents changed'),
        (state / 'run.lock', 'lock contents changed'),
        (state / 'operation.lock', 'lock contents changed'),
    ):
        original_bytes = target.read_bytes()
        target.write_bytes(original_bytes + b'changed\n')
        verify_bootstrap(False, message=error)
        target.write_bytes(original_bytes)
    for target, delta, error in (
        (state, {'uid': 0}, 'ownership, mode or type differs'),
        (state, {'mode': 0o040777}, 'ownership, mode or type differs'),
        (state / 'start.lock', {'uid': 0}, 'ownership, mode or type differs'),
        (state / 'run.lock', {'gid': 0}, 'ownership, mode or type differs'),
        (state / 'operation.lock', {'mode': 0o100640}, 'ownership, mode or type differs'),
        (state / 'paused', {'mode': 0o120640}, 'ownership, mode or type differs'),
        (entrypoint, {'mode': 0o100777}, 'ownership, mode or type differs'),
        (control, {'uid': 1000}, 'ownership, mode or type differs'),
        (queue_check, {'nlink': 2}, 'unexpected hard links'),
        (runtime_config, {'gid': 0}, 'ownership, mode or type differs'),
        (backup / 'paused', {'mode': 0o120600}, 'private regular bootstrap backup'),
        (backup / 'runtime.conf', {'uid': 1000}, 'private regular bootstrap backup'),
        (live / 'shared.env', {'mode': 0o100644}, 'ownership or mode changed'),
        (live / 'config.php', {'uid': 33}, 'ownership or mode changed'),
    ):
        altered_metadata = dict(metadata)
        altered_metadata[target.as_posix()] = dict(metadata.get(target.as_posix(), {}), **delta)
        verify_bootstrap(False, {'metadata': altered_metadata}, error)
    for target, error in (
        (state / 'unexpected', 'unexpected or missing entries'),
        (live / 'config.php.screening-next-123', 'configuration candidate exists'),
        (live / 'shared.env.screening-next-123', 'environment candidate exists'),
    ):
        target.write_bytes(b'leftover')
        verify_bootstrap(False, message=error)
        target.unlink()
    missing = state / 'paused'
    missing.unlink()
    verify_bootstrap(False, message='unexpected or missing entries')
    missing.write_bytes(marker)

    # Run the installer's real Bash guards; service status is mocked and no service
    # command can reach the host. Partial state fails; a complete bootstrap shape
    # is only eligible for the exact Python validation when explicitly resuming.
    guard = re.search(r'(?ms)^  if \[\[ ! -e "\$STATE_DIR".*?^  fi\n', installer).group()
    environment = os.environ.copy()
    environment['RESUMING'] = '0'
    for key in ('STATE_DIR', 'ENTRYPOINT', 'CONTROL', 'QUEUE_CHECK', 'RUNTIME_CONFIG'):
        environment[key] = (base / ('installed-' + key)).as_posix()
    shell_prefix = "set -Eeuo pipefail\nfail() { printf '%s\\n' \"$*\" >&2; exit 1; }\n"
    def shell(source, expected=0, environment=environment):
        result = subprocess.run([bash, '-c', shell_prefix + source], env=environment, text=True, capture_output=True)
        assert result.returncode == expected, result.stdout + result.stderr
        return result
    shell(guard)
    checks += 1
    for key in ('STATE_DIR', 'ENTRYPOINT', 'CONTROL', 'QUEUE_CHECK', 'RUNTIME_CONFIG'):
        target = pathlib.Path(environment[key])
        target.write_bytes(b'partial installation')
        shell(guard, 1)
        target.unlink()
        checks += 1
    pathlib.Path(environment['STATE_DIR']).mkdir()
    for key in ('ENTRYPOINT', 'CONTROL', 'QUEUE_CHECK', 'RUNTIME_CONFIG'):
        pathlib.Path(environment[key]).write_bytes(b'complete shape; byte guards still apply')
    shell(guard, 1)
    checks += 1
    environment['RESUMING'] = '1'
    shell(guard + '\n[[ "$RESUME_PHASE" == bootstrap ]]')
    checks += 1

    # Execute the actual continuation branch with all mutating commands poisoned.
    # A bootstrap recovery must preserve its existing files and lock inodes and
    # proceed directly to the cache update; recreating them would fail this test.
    branch_start = installer.index('if [[ "$RESUME_PHASE" == preinstall ]]; then\nusermod')
    branch_end = installer.index('# Change this one cached value', branch_start)
    continuation = installer[branch_start:branch_end]
    before = {path: (path.read_bytes(), path.stat().st_ino) for path in base.rglob('*') if path.is_file()}
    poisoned = '''RESUME_PHASE=bootstrap
log() { printf '%s\\n' "$*"; }
for command in usermod install python3 chown chmod mv rm; do
    eval "$command() { printf 'Unexpected mutation: %s\\\\n' '$command' >&2; exit 98; }"
done
'''
    result = shell(poisoned + continuation)
    assert 'Keeping the verified bootstrap gate' in result.stdout
    assert before == {path: (path.read_bytes(), path.stat().st_ino) for path in base.rglob('*') if path.is_file()}
    checks += 1

    # The real shell guards pin phase and lock identities between repeated checks.
    identity_start = installer.index('  if [[ -n "$VERIFIED_RESUME_PHASE"')
    identity_end = installer.index('\n}\n', identity_start)
    identity_check = 'verify_identity() {\n' + installer[identity_start:identity_end] + '\n}\nverify_identity\n'
    identity_environment = dict(environment, STATE_DIR=state.as_posix(), BACKUP=backup.as_posix())
    initialized = "RESUME_PHASE=bootstrap\nVERIFIED_RESUME_PHASE=''\nRESUME_LOCK_IDENTITIES=''\nlog() { :; }\n"
    shell(initialized + identity_check + '\n' + identity_check, environment=identity_environment)
    checks += 1
    shell(initialized + identity_check + '\nRESUME_PHASE=preinstall\n' + identity_check, 1, identity_environment)
    checks += 1
    shell(initialized + identity_check + '\nRESUME_LOCK_IDENTITIES=changed\n' + identity_check, 1, identity_environment)
    checks += 1
    worker_guard = re.search(r'(?ms)^    \[\[ "\$\(supervisorctl status.*?\n      \|\| fail \'Resume requires.*?\n', installer).group()
    for status, pid, expected in (('STOPPED', '0', 0), ('RUNNING', '12345', 1), ('STOPPED', '12345', 1), ('STARTING', '0', 1)):
        mock_worker = "PROCESS=fixture\nsupervisorctl() { if [[ $1 == status ]]; then printf 'fixture %s\\n' '" + status + "'; else printf '%s\\n' '" + pid + "'; fi; }\n"
        shell(mock_worker + worker_guard, expected)
        checks += 1

    # Deliver real Bash signals to the extracted handlers; interruption must preserve
    # the checkpoint, exit nonzero and never continue or automatically roll back.
    handlers = function('on_failure') + '\n' + function('on_signal') + '\n'
    traps = '\n'.join(re.findall(r'^trap (?:on_failure EXIT|\'on_signal .*?\' (?:INT|TERM|HUP))$', installer, re.M))
    for signal, status in (('INT', 130), ('TERM', 143), ('HUP', 129)):
        source = "BACKUP=/private/reviewed-checkpoint\nCHANGED=1\nlog() { printf '%s\\n' \"$*\"; }\n"
        source += "crontab() { echo UNEXPECTED_MUTATION; exit 98; }; supervisorctl() { echo UNEXPECTED_MUTATION; exit 98; }; systemctl() { echo UNEXPECTED_MUTATION; exit 98; }\n"
        result = shell(source + handlers + traps + '\nkill -' + signal + ' $$\necho UNEXPECTED_CONTINUATION\n', status)
        output = result.stdout + result.stderr
        assert '/private/reviewed-checkpoint' in output and 'no automatic old-config rollback' in output
        assert 'UNEXPECTED_' not in output
        checks += 1

# A virtual monotonic clock and /proc boundary exercise the unchanged scanner without
# sleeping or reading host processes. Secrets in argv must never appear in progress.
wait_mock = '''
import os, pathlib, time
clock = [0.0]
time.monotonic = lambda: clock[0]
def sleep(seconds): clock[0] += seconds
time.sleep = sleep
def iterdir(path):
    assert path.as_posix() == '/proc'
    return [pathlib.Path('/proc/43210')] if clock[0] < FIXTURE['task_until'] else []
def read_bytes(path):
    assert path.name == 'cmdline'
    return FIXTURE['argv'].encode()
def read_text(path, *a, **k):
    assert path.name == 'comm'
    return 'php\\x1b[31m\\n'
def readlink(path):
    assert pathlib.Path(path).name == 'cwd'
    return '/var/www/homecare'
pathlib.Path.iterdir, pathlib.Path.read_bytes, pathlib.Path.read_text = iterdir, read_bytes, read_text
os.readlink = readlink
'''
for task_until, argv, success, minimum_elapsed in (
    (0, '', True, 65),
    (72, '/usr/bin/php\0/var/www/homecare/artisan\0fixture-secret\0', True, 72),
    (70, '/bin/sh\0-c\0cd /var/www/homecare && php artisan schedule:run fixture-secret\0', True, 70),
    (70, '/usr/bin/php\0artisan\0fixture-secret\0', True, 70),
    (9999, '/usr/bin/php\0/var/www/homecare/artisan\0fixture-secret\0', False, 1800),
):
    fixture = dict(task_until=task_until, argv=argv)
    ending = '\nprint("ELAPSED=" + str(clock[0]))\n'
    result = subprocess.run([sys.executable, '-', '/var/www/homecare', release, '65'],
                            input='FIXTURE = ' + repr(fixture) + '\n' + wait_mock + waiter + ending,
                            text=True, capture_output=True)
    assert (result.returncode == 0) == success, result.stdout + result.stderr
    output = result.stdout + result.stderr
    assert 'minimum cron reload wait: 65s' in output and 'fixture-secret' not in output and '\x1b' not in output
    if success:
        elapsed = float(re.search(r'ELAPSED=([0-9.]+)', output).group(1))
        assert minimum_elapsed <= elapsed <= minimum_elapsed + 2
        assert 'have drained' in output
    else:
        assert 'PIDs 43210' in output and 'no task was killed' in output
    if task_until:
        assert '43210/php' in output
        if task_until > 70:
            assert 'minimum cron reload wait: 0s' in output
    checks += 1

# The read-only modes exit before lock creation. Resume gets a fresh cron reload
# interval and repeats checkpoint validation after draining, before installation.
assert installer.index("log 'Read-only check complete;") < installer.index('exec 9>/var/lock/')
assert 'cron_reload_wait=65\nfi\nwait_for_legacy_tasks "$cron_reload_wait"\nif [[ "$RESUMING" -eq 1 ]]; then verify_original_configuration; fi' in installer
assert installer.index('if [[ "$RESUMING" -eq 1 ]]; then verify_original_configuration; fi') < installer.index('usermod -a -G www-data ubuntu')
checks += 1
print(f'Supervisor setup recovery passed ({checks} checks; isolated checkpoints, signals and virtual-time drain).')
PY
