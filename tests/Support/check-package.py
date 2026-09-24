"""Проверка двух форматов дистрибутива и установки без dev-зависимостей."""
import argparse
import fnmatch
import hashlib
import json
import os
from pathlib import Path
import subprocess
import tarfile
import tempfile

ROOT = Path(__file__).resolve().parents[2]
parser = argparse.ArgumentParser(description=__doc__)
parser.add_argument('--staged', action='store_true', help='Проверить Git index вместо HEAD')
parser.add_argument('--report', type=Path, help='Сохранить JSON с составом и результатами')
args = parser.parse_args()
PHP = os.environ.get('APISUTRA_PHP', 'php')


def run(command, cwd=ROOT):
    result = subprocess.run(command, cwd=cwd, text=True, stdout=subprocess.PIPE, stderr=subprocess.STDOUT)
    if result.returncode:
        raise RuntimeError(f'{command}:\n{result.stdout}')
    return result.stdout.strip()


ref = run(['git', 'write-tree']) if args.staged else run(['git', 'rev-parse', 'HEAD'])
excluded = json.loads((ROOT / 'composer.json').read_text())['archive']['exclude']
required = ['composer.json', 'README.md', 'CHANGELOG.md', 'LICENSE', 'src/Core/AbstractClient.php', 'docs/en/README.md', 'docs/ru/README.md', 'docs/translations.json', 'src/RateLimiting/Resources/acquire.lua', 'src/RateLimiting/Cooldown/Resources/extend.lua']
required += ['bin/apisutra', 'resources/stubs/client.stub', 'resources/stubs/request.stub', 'resources/stubs/dto.stub']
required += [str(path.relative_to(ROOT)) for path in (ROOT / 'docs').rglob('*')
             if path.is_file() and not fnmatch.fnmatch(str(path.relative_to(ROOT)), 'docs/*/development/*')]
smokes = sorted((ROOT / 'tests/Support').glob('standalone-*.php'))
report = {'git_reference': ref, 'php': run([PHP, '-r', 'echo PHP_VERSION;']), 'archives': {}}
with tempfile.TemporaryDirectory(prefix='apisutra-dist-') as temporary:
    folder = Path(temporary).resolve()
    run(['git', 'archive', '--format=tar', f'--output={folder / "git.tar"}', ref])
    run(['composer', 'archive', '--format=tar', f'--dir={folder}', '--file=composer', '--no-interaction'])
    contents = {}
    for kind in ['git', 'composer']:
        archive_path = folder / f'{kind}.tar'
        checkout = folder / kind
        checkout.mkdir()
        with tarfile.open(archive_path) as archive:
            names = [member.name.removeprefix('./').rstrip('/') for member in archive.getmembers()]
            for name in names:
                if any(fnmatch.fnmatchcase(name, path.lstrip('/')) or fnmatch.fnmatchcase(name, path.lstrip('/') + '/*') for path in excluded):
                    raise RuntimeError(f'{kind}: лишний файл {name}')
            if not all(name in names for name in required):
                raise RuntimeError(f'{kind}: неполный дистрибутив')
            for member in archive.getmembers():
                target = (checkout / member.name).resolve()
                if checkout not in target.parents or not (member.isfile() or member.isdir()):
                    raise RuntimeError(f'{kind}: неподдерживаемый entry {member.name}')
            archive.extractall(checkout)
        contents[kind] = {str(p.relative_to(checkout)): hashlib.sha256(p.read_bytes()).hexdigest()
                          for p in checkout.rglob('*') if p.is_file()}
        run(['python3', str(ROOT / 'tests/Support/check-docs.py'), '--root', str(checkout), '--navigation-only', '--distribution'])
        run(['composer', 'validate', '--strict'], checkout)
        run(['composer', 'install', '--no-dev', '--no-interaction', '--prefer-dist', '--no-progress'], checkout)
        run(['composer', 'dump-autoload', '--no-dev', '--optimize', '--strict-psr'], checkout)
        for smoke in smokes:
            print(f'{kind}: {smoke.name}', flush=True)
            run([PHP, str(smoke), str(checkout)])
        print(f'{kind}: installed SDK without Laravel', flush=True)
        sdk_report = folder / f'{kind}-sdk.json'
        run(['python3', str(ROOT / 'tests/Support/check-sdk-package.py'), '--root', str(checkout), '--report', str(sdk_report)])
        report['archives'][kind] = {'bytes': archive_path.stat().st_size,
                                    'files': len(contents[kind]), 'standalone_smokes': len(smokes),
                                    'sdk_installations': json.loads(sdk_report.read_text()),
                                    'file_hashes': contents[kind]}
    if contents['git'] != contents['composer']:
        changed = sorted(name for name in contents['git'].keys() | contents['composer'].keys()
                         if contents['git'].get(name) != contents['composer'].get(name))
        raise RuntimeError('Архивы различаются: ' + ', '.join(changed))
report['status'] = 'passed'
if args.report:
    args.report.write_text(json.dumps(report, ensure_ascii=False, indent=2) + '\n')
print('Git и Composer dist совпадают; все standalone smoke без dev-зависимостей прошли.')
