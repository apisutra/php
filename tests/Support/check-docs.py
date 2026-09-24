"""Навигация, размер, явные PHP-декларации и опубликованные примеры документации."""
import argparse
import json
import os
from pathlib import Path
import re
import subprocess
import sys
sys.path.insert(0, str(Path(__file__).resolve().parent))
from doc_translations import Registry, LIMITS, classify
from urllib.parse import unquote, urlsplit

ROOT = Path(__file__).resolve().parents[2]
BRIDGE = '<!-- documentation-bridge -->'


def is_bridge(content):
    return content.lstrip().startswith(BRIDGE + "\n")


def constructor_signature(source, class_name):
    """Читает декларацию атрибута как текст, не вычисляя PHP default-выражения."""
    match = re.search(r'\bfunction\s+__construct\s*\(', source)
    if match is None:
        return class_name + '()'
    start = end = match.end()
    depth, quote, escaped = 1, None, False
    while end < len(source) and depth:
        char = source[end]
        if quote:
            if escaped:
                escaped = False
            elif char == '\\':
                escaped = True
            elif char == quote:
                quote = None
        elif char in "'\"":
            quote = char
        elif char == '(':
            depth += 1
        elif char == ')':
            depth -= 1
        end += 1
    if depth:
        raise ValueError('Не закрыта декларация конструктора')
    parameters = re.sub(r'/\*.*?\*/', '', source[start:end - 1], flags=re.S)
    parameters = re.sub(r'\b(?:public|protected|private|readonly)\s+', '', parameters)
    parameters = ' '.join(parameters.split()).removesuffix(',')
    return class_name + '(' + parameters + ')'


def declaration_errors(root, manifest):
    errors = []
    registry = Registry(root) if (root / 'docs/translations.json').exists() else None
    for entry in manifest['declarations']:
        declared = entry['docs']
        if registry:
            declared = []
            for identifier in entry['docs']:
                resolved = registry.resolve(identifier)
                if not resolved:
                    errors.append(f'Реестр API: неизвестный ID {identifier}')
                declared.extend(resolved)
        for link in declared:
            parsed = urlsplit(link)
            path = root / parsed.path
            if not path.is_file():
                errors.append(f'Реестр API: отсутствует {link}')
            elif parsed.fragment and parsed.fragment not in anchors(path.read_text()):
                errors.append(f'Реестр API: отсутствует якорь {link}')
        if 'constructor_source' not in entry:
            continue
        actual = constructor_signature((root / entry['source']).read_text(), entry['class'].split('\\')[-1])
        if actual != entry['constructor_source']:
            errors.append(f"{entry['class']}: изменена декларация/default в src")
        expected = '`' + entry['constructor_source'].replace('|', '\\|') + '`'
        documents = registry.resolve(entry['signature_doc']) if registry else [entry['signature_doc']]
        if not documents:
            errors.append(f"Реестр API: неизвестный ID {entry['signature_doc']}")
        for document in documents:
            path = root / urlsplit(document).path
            if not path.is_file() or expected not in path.read_text():
                errors.append(f"{document}: отсутствует точная декларация {entry['class']}")
    return errors


def without_fences(content):
    lines = []
    fence = None
    for line in content.splitlines():
        match = re.match(r'^\s{0,3}(`{3,}|~{3,})', line)
        if match:
            marker = match[1]
            if fence is None:
                fence = marker
            elif marker[0] == fence[0] and len(marker) >= len(fence):
                fence = None
            lines.append('')
        else:
            lines.append('' if fence else line)
    return '\n'.join(lines)


def headings(content):
    used = set()
    result = []
    for title in re.findall(r'^#{1,6}\s+(.+?)(?:\s+#+)?$', without_fences(content), re.M):
        title = re.sub(r'\[([^\]]+)\]\([^)]*\)', r'\1', title)
        title = re.sub(r'<[^>]+>', '', title).strip().lower()
        slug = re.sub(r'[^\w\- ]', '', title).replace(' ', '-')
        candidate, n = slug, 0
        while candidate in used:
            n += 1
            candidate = f'{slug}-{n}'
        used.add(candidate)
        result.append(candidate)
    return result


def anchors(content):
    return set(headings(content)) | set(re.findall(r'\b(?:id|name)=["\']([^"\']+)', without_fences(content)))


def links(content):
    content = without_fences(content)
    # Inline code не содержит навигационных ссылок.
    content = re.sub(r'(`+).*?\1', '', content)
    result = []
    for match in re.finditer(r'\]\(', content):
        start, end, depth = match.end(), match.end(), 1
        while end < len(content) and depth:
            if content[end] == '(' and (end == 0 or content[end - 1] != '\\'):
                depth += 1
            elif content[end] == ')' and (end == 0 or content[end - 1] != '\\'):
                depth -= 1
            end += 1
        if depth:
            continue
        value = content[start:end - 1].strip()
        if value.startswith('<'):
            value = value[1:value.find('>')]
        else:
            value = re.split(r'\s+["\']', value, maxsplit=1)[0]
        result.append(value)
    definitions = dict((key.lower(), value.strip('<>')) for key, value in
                       re.findall(r'^\s{0,3}\[([^\]]+)\]:\s*(<[^>]+>|\S+)', content, re.M))
    # Проверяем и сами definitions: удаление последнего использования не скрывает битую ссылку.
    result.extend(definitions.values())
    for label, identifier in re.findall(r'\[([^\]\n]+)\]\[([^\]\n]*)\]', content):
        key = (identifier or label).lower()
        if key not in definitions:
            result.append('missing-reference:' + key)
    result.extend(re.findall(r'<(?:img|a)\b[^>]*?\b(?:src|href)=["\']([^"\']+)', content))
    return result


def is_development(name):
    return (name == 'CONTRIBUTING.md' or name == 'docs/development'
            or name.startswith('docs/development/')
            or bool(re.match(r'^docs/[^/]+/development(?:/|$)', name)))


def line_limit(name, content):
    if name == 'CHANGELOG.md' or name.startswith('docs/migration/v'):
        return None
    if name == 'README.md':
        return LIMITS['overview']
    if name.endswith('README.md'):
        return 150
    if name.startswith('docs/start/'):
        return 200
    if name.startswith('docs/glossary/'):
        return 180
    if is_development(name):
        return 220
    if name.startswith('docs/reference/'):
        return 320
    return 300


def inspect(root, distribution=False):
    root = root.resolve()
    composer = root / 'composer.json'
    repository = json.loads(composer.read_text()).get('name', 'apisutra/php') if composer.exists() else 'apisutra/php'
    multilingual = (root / 'docs/translations.json').exists() or (root / 'docs/en').exists() or (root / 'docs/ru').exists()
    registry = Registry(root, distribution) if multilingual else None
    paths = [root / 'README.md', root / 'CHANGELOG.md', *sorted((root / 'docs').rglob('*.md'))]
    if (root / 'CONTRIBUTING.md').exists():
        paths.append(root / 'CONTRIBUTING.md')
    content = {p: p.read_text() for p in paths if p.exists()}
    errors, sizes, edges = [], [], {p: set() for p in content}
    if registry:
        errors.extend(registry.errors)
        for name in registry.by_path:
            if (root / name).is_file() and root / name not in content:
                errors.append(f'{name}: документ не охвачен проверкой')
    if distribution:
        for excluded in ('CONTRIBUTING.md', 'AGENTS.md', '.agents'):
            if (root / excluded).exists():
                errors.append(f'{excluded}: непоставляемый ресурс в дистрибутиве')
    count = 0
    for path, body in content.items():
        name = str(path.relative_to(root))
        public = not is_development(name) and (name in ('README.md', 'CHANGELOG.md') or name.startswith('docs/'))
        entry = registry.by_path.get(name) if registry else None
        if registry and not entry:
            errors.append(f'{name}: страница отсутствует в реестре')
        limit = LIMITS[classify(name)[0]] if entry else line_limit(name, body)
        if entry:
            page, language = entry
            switches = re.findall(r'^<!-- languages -->.*<!-- /languages -->$', body, re.M)
            if switches != [registry.switches(page, language)]:
                errors.append(f'{name}: неверный переключатель языков')
            explicit = set(re.findall(r'<a id="([^"]+)"></a>', without_fences(body)))
            if any(not re.fullmatch(r'[A-Za-z0-9_][A-Za-z0-9_.:-]*', anchor) for anchor in explicit):
                errors.append(f'{name}: явные якоря должны использовать английские ID')
            for other in page['paths'].values():
                other_path = root / other
                if other_path.is_file() and explicit != set(re.findall(r'<a id="([^"]+)"></a>', without_fences(other_path.read_text()))):
                    errors.append(f'{name}: явные якоря переводов различаются')
                    break
        length = len(body.splitlines())
        sizes.append({'path': name, 'lines': length, 'bytes': len(body.encode()), 'limit': limit, 'bridge': is_bridge(body)})
        if public:
            if is_bridge(body):
                errors.append(f'{name}: страница-переход вместо актуальной документации')
            prose = without_fences(body)
            if re.search(r'^#{1,6}\s+(?:Прежние разделы|Внутренние и прежние названия)\s*$', prose, re.M):
                errors.append(f'{name}: служебный блок прежних разделов')
            if re.search(r'^\|.*\|\s*Понятие используется в соответствующем контракте SDK\.\s*\|', prose, re.M):
                errors.append(f'{name}: пустое определение словаря')
        if limit is not None and (length > limit or len(body.encode()) > 24576):
            errors.append(f'{name}: размер {length} строк/{len(body.encode())} байт (лимит {limit}/24576)')
        navigation = re.sub(r'^<!-- languages -->.*<!-- /languages -->\n?', '', body, flags=re.M) if registry else body
        # Переключатель проверен отдельно и не создаёт рёбер достижимости.
        for link in links(navigation):
            parsed = urlsplit(link)
            if parsed.scheme == 'missing-reference':
                errors.append(f'{name}: неопределённая ссылка {link}')
                continue
            if parsed.scheme or parsed.netloc:
                match = re.match(r'^https://github.com/' + re.escape(repository) + r'/(?:blob|tree)/[^/]+/(.*)$', link)
                if registry and match and not distribution:
                    external = urlsplit(match[1])
                    target = root / unquote(external.path)
                    if not target.exists():
                        errors.append(f'{name}: отсутствует GitHub-цель {link}')
                    elif external.fragment and target.is_file() and unquote(external.fragment) not in anchors(target.read_text()):
                        errors.append(f'{name}: отсутствует GitHub-якорь {link}')
                    target_entry = registry.by_path.get(unquote(external.path))
                    if entry and target_entry and target_entry[1] != language:
                        errors.append(f'{name}: переход на другой язык вне переключателя: {link}')
                continue
            count += 1
            target = (path.parent / unquote(parsed.path)).resolve() if parsed.path else path
            if not target.is_relative_to(root):
                errors.append(f'{name}: ссылка выходит из пакета: {link}')
                continue
            relative = str(target.relative_to(root))
            if public and (relative.split('/')[0] in {'.agents', '.codex', 'tests', '.github'}
                           or relative == 'AGENTS.md' or is_development(relative)):
                errors.append(f'{name}: ссылка на непоставляемый ресурс: {link}')
            if not target.exists():
                errors.append(f'{name}: отсутствует {link}')
            elif parsed.fragment and target.suffix == '.md':
                if unquote(parsed.fragment) not in anchors(target.read_text()):
                    errors.append(f'{name}: отсутствует якорь {link}')
            target_entry = registry.by_path.get(relative) if registry else None
            if entry and target_entry and language != target_entry[1]:
                fallback = (not registry.languages[language]['required']
                            and language not in target_entry[0]['paths']
                            and target_entry[1] == registry.default
                            and re.search(r'\[[^\]\n]*\(' + re.escape(registry.default)
                                          + r'; translation unavailable\)\]\(' + re.escape(link) + r'\)', navigation))
                if not fallback:
                    errors.append(f'{name}: переход на другой язык вне переключателя: {link}')
            elif target in content:
                edges[path].add(target)
    reachable = set()
    pending = [root / 'README.md', root / 'CONTRIBUTING.md', root / 'docs/development/README.md']
    if registry:
        pending = [root / name for page in registry.pages if isinstance(page, dict)
                   and page.get('id') in ('overview', 'README', 'development/README', 'development/contributing')
                   for name in page.get('paths', {}).values() if (root / name).exists()]
    while pending:
        node = pending.pop()
        if node in reachable:
            continue
        reachable.add(node)
        pending.extend(edges.get(node, ()))
    for path, body in content.items():
        if path not in reachable:
            errors.append(f'{path.relative_to(root)}: нет маршрута из корневого индекса')
    return {'documents': len(content), 'local_links': count, 'sizes': sizes, 'errors': errors}


def instruction_errors(root):
    errors = []
    for path in [root / 'AGENTS.md', *sorted((root / '.agents').glob('*.md'))]:
        if not path.is_file():
            continue
        for link in links(path.read_text()):
            parsed = urlsplit(link)
            if parsed.scheme or parsed.netloc:
                continue
            target = (path.parent / unquote(parsed.path)).resolve() if parsed.path else path
            if not target.is_relative_to(root.resolve()) or not target.exists():
                errors.append(f'{path.relative_to(root)}: отсутствует {link}')
            elif parsed.fragment and target.is_file() and unquote(parsed.fragment) not in anchors(target.read_text()):
                errors.append(f'{path.relative_to(root)}: отсутствует якорь {link}')
    return errors


def run_checks(root):
    result = inspect(root)
    result['errors'].extend(instruction_errors(root))
    manifest = json.loads((ROOT / 'tests/Support/docs-api.json').read_text())
    result['errors'].extend(declaration_errors(root, manifest))
    php = os.environ.get('APISUTRA_PHP', 'php')
    for label, command in [
        ('api', [php, str(ROOT / 'tests/Support/check-docs-api.php'), str(root)]),
        ('translation_snippets', [php, str(ROOT / 'tests/Support/check-docs-translations.php'), str(root)]),
        ('published_sdk', [php, str(ROOT / 'tests/Support/standalone-readme-smoke.php'), str(root)]),
        ('hydration_example', [php, str(ROOT / 'tests/Support/standalone-hydration-rules-smoke.php'), str(root)]),
        ('dto_showcase', [php, str(ROOT / 'tests/Support/standalone-dto-showcase-smoke.php'), str(root)]),
        ('files_example', [php, str(ROOT / 'tests/Support/standalone-files-example-smoke.php'), str(root)]),
        ('result_errors', [php, str(ROOT / 'tests/Support/standalone-result-errors-smoke.php'), str(root)]),
        ('client_showcase', [php, str(ROOT / 'tests/Support/standalone-client-showcase-smoke.php'), str(root)]),
        ('custom_result', [php, str(ROOT / 'tests/Support/standalone-custom-result-smoke.php'), str(root)]),
        ('localization_example', [php, str(ROOT / 'tests/Support/standalone-localization-smoke.php'), str(root)]),
        ('continuation_example', [php, str(ROOT / 'tests/Support/standalone-documentation-continuation-smoke.php'), str(root)]),
    ]:
        run = subprocess.run(command, text=True, capture_output=True)
        result[label] = {'exit_code': run.returncode, 'output': run.stdout.strip(), 'stderr': run.stderr.strip()}
        if run.returncode:
            result['errors'].append(label + ': проверка не прошла')
    return result


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--root', type=Path, default=ROOT)
    parser.add_argument('--report', type=Path)
    parser.add_argument('--navigation-only', action='store_true')
    parser.add_argument('--distribution', action='store_true', help='Проверить только поставляемую область')
    args = parser.parse_args()
    result = inspect(args.root, args.distribution) if args.navigation_only else run_checks(args.root)
    if args.report:
        args.report.write_text(json.dumps(result, ensure_ascii=False, indent=2) + '\n')
    print(f"Документы: {result['documents']}; локальные ссылки: {result['local_links']}; ошибки: {len(result['errors'])}.")
    for error in result['errors']:
        print(error)
    return bool(result['errors'])


if __name__ == '__main__':
    raise SystemExit(main())
