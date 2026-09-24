"""Проверки существенных отказов инструмента документации."""
import importlib.util
import json
from pathlib import Path
import subprocess
import tempfile
import unittest

SUPPORT = Path(__file__).resolve().parent
spec = importlib.util.spec_from_file_location('checker', SUPPORT / 'check-docs.py')
checker = importlib.util.module_from_spec(spec)
spec.loader.exec_module(checker)


class DocumentationChecks(unittest.TestCase):
    def setUp(self):
        self.folder = tempfile.TemporaryDirectory(prefix='apisutra-docs-check-')
        self.addCleanup(self.folder.cleanup)
        self.root = Path(self.folder.name)
        self.write('README.md', '# SDK\n\n[Документация](docs/README.md)\n')
        self.write('CHANGELOG.md', '# Изменения\n')
        self.write('docs/README.md', '# Документация\n\n[История](../CHANGELOG.md)\n')

    def write(self, name, body):
        path = self.root / name
        path.parent.mkdir(parents=True, exist_ok=True)
        path.write_text(body)

    def errors(self):
        return '\n'.join(checker.inspect(self.root)['errors'])

    def test_valid_navigation(self):
        self.assertEqual('', self.errors())

    def test_missing_file_and_fragment(self):
        self.write('docs/README.md', '# Документация\n[Файл](missing.md)\n[Якорь](../README.md#wrong)\n')
        self.assertIn('отсутствует missing.md', self.errors())
        self.assertIn('отсутствует якорь', self.errors())

    def test_unicode_duplicate_html_and_encoded_anchor(self):
        self.write('docs/README.md', '# Документация\n## Пример\n## Пример\n## Пример-1\n'
                   '[Первый](#%D0%BF%D1%80%D0%B8%D0%BC%D0%B5%D1%80)\n'
                   '[Второй](#пример-1)\n[Третий](#пример-1-1)\n'
                   '<a id="original"></a>\n[HTML](#original)\n[История](../CHANGELOG.md)\n')
        self.assertEqual('', self.errors())

    def test_fences_are_not_links_or_headings(self):
        body = '# Документация\n```md\n## Phantom\n[Wrong](absent.md)\n```\n'
        self.assertNotIn('phantom', checker.anchors(body))
        self.assertEqual([], checker.links(body))

    def test_explaining_bridge_marker_does_not_make_a_bridge(self):
        self.assertFalse(checker.is_bridge('# Rules\nUse `' + checker.BRIDGE + '`.\n'))
        self.assertTrue(checker.is_bridge(checker.BRIDGE + '\n# Old\n'))

    def test_constructor_default_is_compared_without_evaluation(self):
        source = '<?php class Probe { public function __construct(public object $value = new Dangerous()) {} }'
        self.write('src/Probe.php', source)
        self.write('docs/README.md', '# Doc\n`Probe(object $value = new Dangerous())`\n')
        entry = {'class': 'Probe', 'docs': ['docs/README.md'], 'source': 'src/Probe.php',
                 'constructor_source': 'Probe(object $value = new Dangerous())', 'signature_doc': 'docs/README.md'}
        self.assertEqual([], checker.declaration_errors(self.root, {'declarations': [entry]}))
        self.write('src/Probe.php', source.replace('Dangerous()', 'Changed()'))
        self.assertIn('изменена декларация/default', checker.declaration_errors(self.root, {'declarations': [entry]})[0])

    def test_reference_links_and_images(self):
        self.write('docs/picture (1).svg', '<svg/>')
        self.write('docs/README.md', '# Документация\n![image](<picture (1).svg>)\n'
                   '[История][history]\n[history]: ../CHANGELOG.md\n')
        self.assertEqual('', self.errors())
        self.write('docs/README.md', '# Документация\n[missing][undefined]\n')
        self.assertIn('неопределённая ссылка', self.errors())

    def test_unshipped_dependency_is_rejected_even_when_present(self):
        self.write('docs/development/detail.md', '# Detail\n')
        self.write('docs/README.md', '# Документация\n[Internal](development/detail.md)\n')
        self.assertIn('непоставляемый ресурс', self.errors())

    def test_development_entry_can_link_checkout_resources(self):
        self.write('docs/development/README.md', '# Разработка\n[Правила](../../AGENTS.md)\n'
                   '[Исходник](../../src/Example.php)\n[Тест](../../tests/ExampleTest.php)\n')
        self.write('AGENTS.md', '# Правила\n')
        self.write('src/Example.php', '<?php\n')
        self.write('tests/ExampleTest.php', '<?php\n')
        self.assertEqual('', self.errors())
        self.write('docs/development/orphan.md', '# Отдельная тема\n')
        self.assertIn('нет маршрута', self.errors())

    def test_development_limits_are_preserved_inside_docs(self):
        self.write('docs/development/README.md', '# Разработка\n[Тема](detail.md)\n')
        self.write('docs/development/detail.md', '# Тема\n' + 'строка\n' * 219)
        self.assertEqual('', self.errors())
        self.write('docs/development/detail.md', '# Тема\n' + 'строка\n' * 220)
        self.assertIn('размер', self.errors())

    def test_orphan_and_size(self):
        self.write('docs/orphan.md', '# Orphan\n')
        self.assertIn('нет маршрута', self.errors())
        self.write('docs/README.md', '# Документация\n[Тема](orphan.md)\n[История](../CHANGELOG.md)\n')
        self.assertEqual('', self.errors())
        self.write('docs/README.md', '# Документация\n' + 'строка\n' * 151)
        self.assertIn('размер', self.errors())

    def test_root_readme_has_room_for_showcase_and_grouped_tables_with_a_fixed_limit(self):
        body = '# SDK\n[Документация](docs/README.md)\n'
        self.write('README.md', body + 'строка\n' * 238)
        self.assertEqual('', self.errors())
        self.write('README.md', body + 'строка\n' * 239)
        self.assertIn('размер', self.errors())

    def test_redirect_cannot_bypass_navigation_or_size_limits(self):
        self.write('docs/old.md', checker.BRIDGE + '\n# Старый адрес\n' + '<a id="alias"></a>\n' * 301)
        errors = self.errors()
        self.assertIn('страница-переход', errors)
        self.assertIn('нет маршрута', errors)
        self.assertIn('размер', errors)

    def test_legacy_section_is_rejected_but_example_is_allowed(self):
        self.write('docs/README.md', '# Документация\n## Прежние разделы\n')
        self.assertIn('служебный блок', self.errors())
        self.write('docs/README.md', '# Документация\n```md\n## Прежние разделы\n```\n[История](../CHANGELOG.md)\n')
        self.assertEqual('', self.errors())

    def test_empty_glossary_definition(self):
        self.write('docs/README.md', '# Документация\n| X | Понятие используется в соответствующем контракте SDK. | [Справка](../README.md) |\n')
        self.assertIn('пустое определение', self.errors())

    def api(self, entry):
        (self.root / 'vendor').symlink_to(SUPPORT.parents[1] / 'vendor', target_is_directory=True)
        manifest = self.root / 'api.json'
        manifest.write_text(json.dumps({'declarations': [entry]}))
        return subprocess.run(['php', str(SUPPORT / 'check-docs-api.php'), str(self.root), str(manifest)],
                              text=True, capture_output=True)

    def test_missing_php_symbol(self):
        result = self.api({'class': 'NotARealApiSutraClass', 'docs': ['README.md']})
        self.assertNotEqual(0, result.returncode)
        self.assertIn('NotARealApiSutraClass', result.stdout)

    def test_wrong_php_parameter(self):
        result = self.api({'class': 'ApiSutra\\Config\\ClientConfig', 'docs': ['README.md'],
                           'methods': {'__construct': {'return': '', 'parameters': {'wrong': {}}}}})
        self.assertNotEqual(0, result.returncode)
        self.assertIn('параметров', result.stdout)

    def test_nonpublic_method(self):
        result = self.api({'class': 'ApiSutra\\Serialization\\Rules\\HydrationRules', 'docs': ['README.md'],
                           'methods': {'__construct': {'return': '', 'parameters': {}}}})
        self.assertNotEqual(0, result.returncode)
        self.assertIn('не public', result.stdout)

    def test_self_return_on_supported_php(self):
        result = self.api({'class': 'ApiSutra\\Config\\ClientConfig', 'docs': ['README.md'],
                           'methods': {'with': {'return': 'self', 'parameters': {
                               'overrides': {'type': 'mixed', 'optional': True, 'variadic': True}}}}})
        self.assertEqual(0, result.returncode, result.stdout)

    def test_static_return_does_not_match_self(self):
        result = self.api({'class': 'ApiSutra\\Config\\ClientConfig', 'docs': ['README.md'],
                           'methods': {'with': {'return': 'static', 'parameters': {
                               'overrides': {'type': 'mixed', 'optional': True, 'variadic': True}}}}})
        self.assertNotEqual(0, result.returncode)
        self.assertIn('возвращаемый тип', result.stdout)

    def test_self_parameter_on_supported_php(self):
        result = self.api({'class': 'ApiSutra\\Config\\DtoHydrationPolicy', 'docs': ['README.md'],
                           'methods': {'merge': {'return': 'self', 'parameters': {
                               'override': {'type': 'self', 'optional': False, 'variadic': False}}}}})
        self.assertEqual(0, result.returncode, result.stdout)

    def test_broken_published_example(self):
        self.write('docs/example/sdk/run.php', '<?php echo "{}";')
        result = subprocess.run(['php', str(SUPPORT / 'standalone-readme-smoke.php'), str(self.root)],
                                text=True, capture_output=True)
        self.assertNotEqual(0, result.returncode)
        self.assertIn('Опубликованный SDK', result.stderr)


if __name__ == '__main__':
    unittest.main()
