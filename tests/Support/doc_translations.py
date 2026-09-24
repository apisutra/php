"""Реестр языковых редакций; никаких автоматических подтверждений перевода."""
import hashlib
from html import escape
import json
from pathlib import Path, PurePosixPath
import re

LIMITS = {'overview': 240, 'index': 150, 'start': 200, 'guide': 300,
          'reference': 320, 'glossary': 180, 'development': 220,
          'page': 300, 'changelog': None, 'release': None}
ROOT_PAGES = {'README.md': ('overview', 'public'),
              'CONTRIBUTING.md': ('development', 'development'),
              'CHANGELOG.md': ('changelog', 'public')}


def digest(text):
    return hashlib.sha256(text.replace('\r\n', '\n').replace('\r', '\n').encode()).hexdigest()


def classify(path):
    if path in ROOT_PAGES:
        return ROOT_PAGES[path]
    parts = PurePosixPath(path).parts
    if len(parts) < 3 or parts[0] != 'docs':
        return None
    name = '/'.join(parts[2:])
    scope = 'development' if name.startswith('development/') else 'public'
    if name == 'overview.md':
        kind = 'overview'
    elif name == 'changelog.md':
        kind = 'changelog'
    elif re.fullmatch(r'migration/v[^/]+\.md', name):
        kind = 'release'
    elif name.endswith('README.md'):
        kind = 'index'
    else:
        kind = {'start': 'start', 'guides': 'guide', 'reference': 'reference',
                'glossary': 'glossary', 'development': 'development'}.get(name.split('/')[0], 'page')
    return kind, scope


def unique_object(pairs):
    result = {}
    for key, value in pairs:
        if key in result:
            raise ValueError(f'повторное поле JSON: {key}')
        result[key] = value
    return result


class Registry:
    def __init__(self, root, distribution=False):
        self.root = root
        self.distribution = distribution
        self.errors = []
        self.pages = []
        self.by_path = {}
        self.by_id = {}
        self.languages = {}
        self.default = 'en'
        try:
            data = json.loads((root / 'docs/translations.json').read_text(), object_pairs_hook=unique_object)
            if not isinstance(data, dict) or data.get('version') != 1:
                raise ValueError('ожидается version=1')
            self.default = data['default_language']
            self.languages = data['languages']
            pages = data['pages']
            if not isinstance(self.languages, dict) or not isinstance(pages, list):
                raise ValueError('languages/pages: неверный тип')
            if self.default != 'en':
                raise ValueError('основной язык должен быть en')
            for lang, config in self.languages.items():
                if not re.fullmatch(r'[a-z]{2,3}(?:-[A-Z]{2})?', lang):
                    raise ValueError('недопустимый код языка')
                if (not isinstance(config, dict) or not isinstance(config.get('required'), bool)
                        or not isinstance(config.get('label'), str) or not config['label'].strip()):
                    raise ValueError('языку нужны label и required')
            if any(not self.languages.get(lang, {}).get('required') for lang in ('en', 'ru')):
                raise ValueError('en и ru обязательны')
            for page in pages:
                self.add(page)
                self.pages.append(page)
        except (OSError, ValueError, KeyError, TypeError, AttributeError) as exc:
            self.errors.append(f'Реестр переводов: {exc}')
            # Невалидную схему нельзя передавать последующим проверкам как частично готовую.
            self.pages = []
            self.by_path = {}
            self.by_id = {}

    def add(self, page):
        identifier = page['id']
        if not isinstance(identifier, str) or not identifier or identifier in self.by_id:
            raise ValueError(f'повторный или неверный ID {identifier}')
        self.by_id[identifier] = page
        if page.get('kind') not in LIMITS or page.get('scope') not in ('public', 'development'):
            raise ValueError(f'{identifier}: неверные kind/scope')
        paths = page['paths']
        if not isinstance(paths, dict) or self.default not in paths:
            raise ValueError(f'{identifier}: отсутствует основной путь')
        for lang in self.languages:
            if self.languages[lang]['required'] and lang not in paths:
                self.errors.append(f'{identifier}: отсутствует обязательный перевод {lang}')
        for lang, name in paths.items():
            if lang not in self.languages or not isinstance(name, str):
                raise ValueError(f'{identifier}: неизвестный язык или путь')
            path = PurePosixPath(name)
            if path.is_absolute() or '..' in path.parts or str(path) != name or path.suffix != '.md':
                raise ValueError(f'{identifier}: небезопасный путь {name}')
            if not name.startswith(f'docs/{lang}/') and not (lang == self.default and name in ROOT_PAGES):
                raise ValueError(f'{identifier}: путь не принадлежит языку {lang}: {name}')
            if name in self.by_path:
                raise ValueError(f'повторный путь {name}')
            self.by_path[name] = (page, lang)
            if classify(name) != (page['kind'], page['scope']):
                self.errors.append(f'{name}: неверная классификация kind/scope')
            physical = self.root / name
            if not physical.resolve().is_relative_to(self.root.resolve()):
                self.errors.append(f'{name}: путь выходит из пакета')
                continue
            if self.distribution and page['scope'] == 'development':
                if physical.exists():
                    self.errors.append(f'{name}: development попал в дистрибутив')
                continue
            if not physical.is_file():
                self.errors.append(f'{identifier}: отсутствует перевод {lang}: {name}')
                continue
            if lang == self.default:
                continue
            source = self.root / paths[self.default]
            review = page.get('reviewed', {}).get(lang, {})
            if not source.is_file() or review.get('source_sha256') != digest(source.read_text()) or review.get('translation_sha256') != digest(physical.read_text()):
                self.errors.append(f'{identifier}/{lang}: устаревшая или отсутствующая смысловая сверка')

    def resolve(self, identifier):
        """Ссылки docs-api задаются ID[#якорь], сигнатура хранится один раз."""
        key, marker, fragment = identifier.partition('#')
        page = self.by_id.get(key)
        if page is None:
            return []
        return [name + (marker + fragment if marker else '') for name in page['paths'].values()]

    def switches(self, page, language):
        """Полный переключатель, включая явно помеченный fallback необязательного языка."""
        import posixpath
        current = page['paths'][language]
        links = []
        for lang, config in self.languages.items():
            target = page['paths'].get(lang, page['paths'][self.default])
            label = config['label']
            if lang not in page['paths']:
                label += f" ({self.default}; translation unavailable)"
            href = posixpath.relpath(target, posixpath.dirname(current) or '.')
            # Начальный HTML-комментарий делает всю строку HTML-блоком в GFM.
            links.append(f'<a href="{escape(href, quote=True)}">{escape(label)}</a>')
        return '<!-- languages --> ' + ' · '.join(links) + ' <!-- /languages -->'
