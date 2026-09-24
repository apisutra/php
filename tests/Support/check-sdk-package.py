"""Установить учебный SDK из проверяемой поставки в независимое PHP-приложение."""
import argparse
import json
import os
from pathlib import Path
import shutil
import subprocess
import tempfile

ROOT = Path(__file__).resolve().parents[2]
parser = argparse.ArgumentParser(description=__doc__)
parser.add_argument('--root', type=Path, required=True, help='Корень проверяемой поставки ApiSutra')
parser.add_argument('--report', type=Path)
args = parser.parse_args()
core = args.root.resolve()
sdk = core / 'docs/example/sdk'
PHP = os.environ.get('APISUTRA_PHP', 'php')
commands = []


def run(command, cwd, environment=None):
    result = subprocess.run(command, cwd=cwd, env=environment, text=True, stdout=subprocess.PIPE, stderr=subprocess.STDOUT)
    commands.append({'command': command, 'exit_code': result.returncode})
    if result.returncode:
        raise RuntimeError(f'{command}:\n{result.stdout}')
    return result.stdout


def repository(path, name, version):
    # Версии принадлежат только стенду; публичный SDK зависит от выпущенной линии ApiSutra.
    return {'type': 'path', 'url': str(path), 'options': {'symlink': False, 'versions': {name: version}}}


report = {'status': 'running', 'commands': commands}
with tempfile.TemporaryDirectory(prefix='apisutra-sdk-install-') as temporary:
    folder = Path(temporary)
    plain = folder / 'standalone'
    plain.mkdir()
    manifest = {
        'name': 'apisutra/standalone-sdk-check', 'type': 'project', 'license': 'proprietary',
        'require': {'example/records-sdk': 'dev-main'},
        'repositories': [repository(sdk, 'example/records-sdk', 'dev-main'), repository(core, 'apisutra/php', '0.1.0')],
        'minimum-stability': 'dev', 'prefer-stable': True,
    }
    (plain / 'composer.json').write_text(json.dumps(manifest, indent=2) + '\n')
    print('SDK: production install without Laravel', flush=True)
    run(['composer', 'validate', '--strict'], sdk)
    run(['composer', 'install', '--no-dev', '--no-interaction', '--prefer-dist', '--no-progress'], plain)
    run(['composer', 'dump-autoload', '--no-dev', '--optimize', '--strict-psr'], plain)
    run([PHP, str(ROOT / 'tests/Support/sdk-package-smoke.php'), str(plain)], plain)
    report['standalone'] = 'passed'

report['status'] = 'passed'
if args.report:
    args.report.write_text(json.dumps(report, ensure_ascii=False, indent=2) + '\n')
print('SDK Composer installs without Laravel — OK.')
