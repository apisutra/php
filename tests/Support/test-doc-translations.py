"""Негативные сценарии реестра, навигации, деклараций и области поставки."""
import importlib.util
from html.parser import HTMLParser
import json
import subprocess
import tarfile
from pathlib import Path
import tempfile
import unittest
from doc_translations import Registry, digest

spec = importlib.util.spec_from_file_location('checker', Path(__file__).with_name('check-docs.py'))
checker = importlib.util.module_from_spec(spec)
spec.loader.exec_module(checker)


class SwitchLinks(HTMLParser):
    def __init__(self):
        super().__init__()
        self.links = []

    def handle_starttag(self, tag, attrs):
        if tag == 'a':
            self.links.append(dict(attrs).get('href'))


class TranslationChecks(unittest.TestCase):
    def setUp(self):
        self.folder = tempfile.TemporaryDirectory()
        self.addCleanup(self.folder.cleanup)
        self.root = Path(self.folder.name)
        self.data = {'version': 1, 'default_language': 'en', 'languages': {
            'en': {'label': 'English', 'required': True}, 'ru': {'label': 'Русский', 'required': True}},
            'pages': [
                {'id': 'overview', 'kind': 'overview', 'scope': 'public',
                 'paths': {'en': 'README.md', 'ru': 'docs/ru/overview.md'}},
                {'id': 'reference/detail', 'kind': 'reference', 'scope': 'public',
                 'paths': {'en': 'docs/en/reference/detail.md', 'ru': 'docs/ru/reference/detail.md'}},
                {'id': 'development/README', 'kind': 'index', 'scope': 'development',
                 'paths': {'en': 'docs/en/development/README.md', 'ru': 'docs/ru/development/README.md'}}]}
        self.write('README.md', '# SDK\n[Detail](docs/en/reference/detail.md)\n')
        self.write('docs/ru/overview.md', '# SDK\n[Раздел](reference/detail.md)\n')
        for lang in ('en', 'ru'):
            self.write(f'docs/{lang}/reference/detail.md', '# Detail\n<a id="contract"></a>\n`Probe(int $n = 1)`\n')
            self.write(f'docs/{lang}/development/README.md', '# Development\n')
        self.seal()

    def write(self, name, body):
        path = self.root / name
        path.parent.mkdir(parents=True, exist_ok=True)
        path.write_text(body)

    def save(self):
        self.write('docs/translations.json', json.dumps(self.data))

    def seal(self):
        self.save()
        registry = Registry(self.root)
        for page in self.data['pages']:
            for lang, name in page['paths'].items():
                body = (self.root / name).read_text()
                body = checker.re.sub(r'^<!-- languages -->.*<!-- /languages -->\n', '', body, flags=checker.re.M)
                self.write(name, registry.switches(page, lang) + '\n' + body)
            page['reviewed'] = {lang: {
                'source_sha256': digest((self.root / page['paths']['en']).read_text()),
                'translation_sha256': digest((self.root / name).read_text())}
                for lang, name in page['paths'].items() if lang != 'en'}
        self.save()

    def errors(self, distribution=False):
        return '\n'.join(checker.inspect(self.root, distribution)['errors'])

    def test_valid_and_line_ending_normalization(self):
        self.assertEqual('', self.errors())
        self.assertEqual(digest('a\nb\n'), digest('a\r\nb\r\n'))

    def test_language_switch_links_work_inside_a_gfm_html_block(self):
        # GFM не разбирает Markdown внутри строки, начинающейся HTML-комментарием.
        for name, expected in [
            ('README.md', ['README.md', 'docs/ru/overview.md']),
            ('docs/ru/overview.md', ['../../README.md', 'overview.md']),
        ]:
            with self.subTest(page=name):
                line = (self.root / name).read_text().splitlines()[0]
                parser = SwitchLinks()
                parser.feed(line)
                self.assertEqual(expected, parser.links)

    def test_missing_required_file_or_mapping(self):
        (self.root / 'docs/ru/reference/detail.md').unlink()
        self.assertIn('отсутствует перевод ru', self.errors())
        del self.data['pages'][1]['paths']['ru']
        self.save()
        self.assertIn('отсутствует обязательный перевод ru', self.errors())

    def test_required_languages_cannot_be_disabled(self):
        self.data['languages']['ru']['required'] = False
        self.save()
        self.assertIn('en и ru обязательны', self.errors())

    def test_either_side_stales_review_without_rewriting_manifest(self):
        before = (self.root / 'docs/translations.json').read_bytes()
        for name in ('README.md', 'docs/ru/overview.md'):
            original = (self.root / name).read_text()
            self.write(name, original + '\nChanged\n')
            self.assertIn('устаревшая', self.errors())
            self.write(name, original)
        self.assertEqual(before, (self.root / 'docs/translations.json').read_bytes())

    def test_missing_registry(self):
        (self.root / 'docs/translations.json').unlink()
        self.assertIn('Реестр переводов', self.errors())

    def test_unregistered_and_duplicate_paths(self):
        self.write('docs/en/extra.md', '# Extra\n')
        self.assertIn('страница отсутствует в реестре', self.errors())
        self.data['pages'].append(dict(self.data['pages'][1], id='duplicate'))
        self.save()
        self.assertIn('повторный путь', self.errors())

    def test_misclassification_does_not_disable_limits_or_distribution(self):
        self.data['pages'][1]['kind'] = 'release'
        self.data['pages'][1]['scope'] = 'development'
        self.save()
        self.assertIn('неверная классификация', self.errors())

    def test_wrong_locale_and_switches_do_not_rescue_orphan(self):
        self.write('docs/ru/overview.md', '# SDK\n[Wrong](../en/reference/detail.md)\n')
        self.seal()
        errors = self.errors()
        self.assertIn('переход на другой язык', errors)
        self.assertIn('docs/ru/reference/detail.md: нет маршрута', errors)

    def test_switch_target_and_shared_anchor(self):
        name = 'docs/ru/reference/detail.md'
        self.write(name, (self.root / name).read_text().replace('detail.md"', 'missing.md"').replace('id="contract"', 'id="different"'))
        self.assertIn('неверный переключатель', self.errors())
        self.assertIn('якоря переводов различаются', self.errors())

    def test_third_optional_language_and_explicit_fallback(self):
        self.data['languages']['de'] = {'label': 'Deutsch', 'required': False}
        self.data['pages'][0]['paths']['de'] = 'docs/de/overview.md'
        self.write('docs/de/overview.md', '# SDK\n[Detail (en; translation unavailable)](../en/reference/detail.md)\n')
        self.seal()
        self.assertEqual('', self.errors())
        self.assertIn('Deutsch (en; translation unavailable)', (self.root / 'docs/en/reference/detail.md').read_text())
        self.write('docs/de/overview.md', (self.root / 'docs/de/overview.md').read_text() + 'Changed\n')
        self.assertIn('устаревшая', self.errors())

    def test_distribution_omits_development_but_rejects_leaks(self):
        self.assertIn('development попал', self.errors(distribution=True))
        for lang in ('en', 'ru'):
            (self.root / f'docs/{lang}/development/README.md').unlink()
        self.assertEqual('', self.errors(distribution=True))
        self.assertIn('отсутствует перевод', self.errors())

    def test_api_signature_is_checked_in_each_language(self):
        self.write('src/Probe.php', '<?php class Probe { public function __construct(int $n = 1) {} }')
        manifest = {'declarations': [{'class': 'Probe', 'source': 'src/Probe.php',
                    'constructor_source': 'Probe(int $n = 1)', 'docs': ['reference/detail#contract'],
                    'signature_doc': 'reference/detail'}]}
        self.assertEqual([], checker.declaration_errors(self.root, manifest))
        name = 'docs/ru/reference/detail.md'
        self.write(name, (self.root / name).read_text().replace('$n = 1', '$n = 2'))
        self.assertIn('отсутствует точная декларация', '\n'.join(checker.declaration_errors(self.root, manifest)))


    def test_root_overview_limit_cannot_be_bypassed_by_locale(self):
        self.write('docs/ru/overview.md', '# SDK\n' + 'text\n' * 240)
        self.seal()
        self.assertIn('размер', self.errors())

    def test_manifest_rejects_path_escape(self):
        self.data['pages'][1]['paths']['ru'] = '../outside.md'
        self.save()
        self.assertIn('небезопасный путь', self.errors())

    def test_php_comments_may_change_but_literals_and_tokens_may_not(self):
        helper = str(Path(__file__).with_name('documentation-guides.php'))
        code = """require $argv[1];
        $a = 'return "// текст"; // русский';
        $b = 'return "// текст"; // English';
        $c = 'return "// translated";';
        echo json_encode([
            documentationPhp($a) === documentationPhp($b),
            documentationPhp($a) === documentationPhp($c),
            documentationPhp('return foo;') === documentationPhp('returnfoo;'),
        ]);"""
        run = subprocess.run(['php', '-r', code, helper], capture_output=True, text=True, check=True)
        self.assertEqual([True, False, False], json.loads(run.stdout))

    def test_real_archive_rules_exclude_development_for_future_locale(self):
        repository = Path(__file__).resolve().parents[2]
        folder = self.root / 'archive-fixture'
        folder.mkdir()
        (folder / '.gitattributes').write_text((repository / '.gitattributes').read_text())
        exclude = json.loads((repository / 'composer.json').read_text())['archive']['exclude']
        (folder / 'composer.json').write_text(json.dumps({
            'name': 'fixture/documentation', 'description': 'Fixture', 'license': 'MIT',
            'archive': {'exclude': exclude}}))
        expected = []
        forbidden = ['CONTRIBUTING.md', 'AGENTS.md', '.agents/README.md']
        for lang in ('en', 'ru', 'de'):
            expected.append(f'docs/{lang}/README.md')
            forbidden.append(f'docs/{lang}/development/README.md')
        for name in expected + forbidden:
            path = folder / name
            path.parent.mkdir(parents=True, exist_ok=True)
            path.write_text('# Fixture\n')
        for command in [['git', 'init', '-q'], ['git', 'add', '.']]:
            subprocess.run(command, cwd=folder, capture_output=True, check=True)
        tree = subprocess.check_output(['git', 'write-tree'], cwd=folder, text=True).strip()
        subprocess.run(['git', 'archive', '--format=tar', '-o', str(self.root / 'git.tar'), tree],
                       cwd=folder, capture_output=True, check=True)
        subprocess.run(['composer', 'archive', '--format=tar', '--dir=' + str(self.root),
                        '--file=composer', '--no-interaction'], cwd=folder, capture_output=True, check=True)
        for kind in ('git', 'composer'):
            with tarfile.open(self.root / (kind + '.tar')) as archive:
                names = {m.name.removeprefix('./').rstrip('/') for m in archive.getmembers()}
            self.assertTrue(set(expected) <= names, kind)
            self.assertFalse(set(forbidden) & names, kind)



    def test_duplicate_language_key_in_json_is_rejected(self):
        self.save()
        path = self.root / 'docs/translations.json'
        path.write_text(path.read_text().replace('"en": "README.md"', '"en": "README.md", "en": "docs/en/overview.md"'))
        self.assertIn('повторное поле JSON', self.errors())

    def test_missing_required_english_file(self):
        (self.root / 'README.md').unlink()
        self.assertIn('отсутствует перевод en', self.errors())

    def test_public_links_to_local_development_are_rejected(self):
        self.write('README.md', '# SDK\n[Internal](docs/en/development/README.md)\n')
        self.seal()
        self.assertIn('непоставляемый ресурс', self.errors())

    def test_own_github_links_are_checked_in_checkout(self):
        self.write('README.md', '# SDK\n[Missing](https://github.com/apisutra/php/blob/master/docs/en/development/missing.md)\n')
        self.seal()
        self.assertIn('отсутствует GitHub-цель', self.errors())



    def test_multiple_paths_for_one_language_are_rejected(self):
        self.data['pages'][1]['paths']['ru'] = ['docs/ru/reference/detail.md', 'docs/ru/other.md']
        self.save()
        self.assertIn('неизвестный язык или путь', self.errors())



    def test_shared_anchors_must_not_depend_on_translated_heading(self):
        for lang in ('en', 'ru'):
            name = f'docs/{lang}/reference/detail.md'
            self.write(name, (self.root / name).read_text().replace('id="contract"', 'id="контракт"'))
        self.seal()
        self.assertIn('английские ID', self.errors())

    def test_encoded_github_path_does_not_bypass_locale_isolation(self):
        path = self.root / 'README.md'
        path.write_text(path.read_text() +
                        '[Other edition](https://github.com/apisutra/php/blob/master/docs/%72u/reference/detail.md)\n')
        self.seal()
        self.assertIn('переход на другой язык', self.errors())

    def test_optional_language_label_must_be_text(self):
        self.data['languages']['de'] = {'label': 17, 'required': False}
        self.save()
        self.assertIn('языку нужны label и required', self.errors())


if __name__ == '__main__':
    unittest.main()
