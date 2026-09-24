"""Проверка подсчёта сценариев и отображения неполных/неуспешных прогонов."""

from __future__ import annotations

import importlib.util
from pathlib import Path
from tempfile import TemporaryDirectory
import unittest
import xml.etree.ElementTree as ET

spec = importlib.util.spec_from_file_location('badge', Path(__file__).with_name('test-count-badge.py'))
badge = importlib.util.module_from_spec(spec)
spec.loader.exec_module(badge)


class TestCountBadgeTest(unittest.TestCase):
    def render(self, xml: str | None, outcome: str = 'success') -> dict:
        with TemporaryDirectory() as temporary:
            report = Path(temporary) / 'junit.xml'
            if xml is not None:
                report.write_text(xml)
            return badge.build_badge(report, outcome)

    def test_nested_suites_and_datasets_are_counted_once(self):
        result = self.render('''<testsuites><testsuite tests="2"><testsuite tests="2">
            <testcase name="dataset 1"/><testcase name="dataset 2"/>
            </testsuite></testsuite></testsuites>''')
        self.assertEqual(result['message'], '2 passed')
        self.assertEqual(result['color'], 'brightgreen')

    def test_failures_errors_and_skips_are_not_passes(self):
        result = self.render('''<testsuite><testcase/><testcase><failure/></testcase>
            <testcase><error/></testcase><testcase><skipped/></testcase></testsuite>''', 'failure')
        self.assertEqual(result['message'], '1 passed, 2 failed, 1 skipped')
        self.assertEqual(result['color'], 'red')

    def test_skipped_run_is_yellow(self):
        self.assertEqual(self.render('<testsuite><testcase><skipped/></testcase></testsuite>')['color'], 'yellow')

    def test_diagnostics_can_fail_a_run_with_passing_cases(self):
        result = self.render('<testsuite><testcase/></testsuite>', 'failure')
        self.assertEqual(result['message'], '1 passed, run failed')
        self.assertEqual(result['color'], 'red')

    def test_missing_or_empty_report_is_unavailable(self):
        for xml in [None, '<testsuites/>']:
            with self.subTest(xml=xml):
                result = self.render(xml, 'failure')
                self.assertEqual(result['message'], 'unavailable')
                self.assertEqual(result['color'], 'lightgrey')

    def test_malformed_report_is_rejected(self):
        with self.assertRaises(ET.ParseError):
            self.render('<broken')

    def test_truncated_utf8_dataset_name_does_not_break_counting(self):
        with TemporaryDirectory() as temporary:
            report = Path(temporary) / 'junit.xml'
            report.write_bytes(b'<testsuite name="dataset \xd0"><testcase/></testsuite>')
            self.assertEqual(badge.build_badge(report, 'success')['message'], '1 passed')

    def render_reports(self, documents: list[str | None], outcome: str = 'success') -> dict:
        with TemporaryDirectory() as temporary:
            reports = [Path(temporary) / f'{index}.xml' for index in range(len(documents))]
            for report, xml in zip(reports, documents):
                if xml is not None:
                    report.write_text(xml)
            return badge.build_badge(reports[0], outcome, *reports[1:])

    def test_redis_pass_covers_core_skip_without_double_counting(self):
        core = '''<testsuite><testcase classname="Core" name="base"/>
            <testcase classname="Redis" name="quota" file="/host/test.php"><skipped/></testcase></testsuite>'''
        redis = '<testsuite><testcase classname="Redis" name="quota" file="/app/test.php"/></testsuite>'
        for documents in [[core, redis], [redis, core]]:
            result = self.render_reports(documents)
            self.assertEqual(result['message'], '2 passed')
            self.assertEqual(result['color'], 'brightgreen')

    def test_failure_is_never_hidden_by_another_pass(self):
        passed = '<testsuite><testcase classname="Redis" name="quota"/></testsuite>'
        failed = '<testsuite><testcase classname="Redis" name="quota"><failure/></testcase></testsuite>'
        for documents in [[passed, failed], [failed, passed]]:
            result = self.render_reports(documents)
            self.assertEqual(result['message'], '0 passed, 1 failed')
            self.assertEqual(result['color'], 'red')

    def test_uncovered_skip_remains_visible(self):
        result = self.render_reports([
            '<testsuite><testcase classname="Core" name="zip"><skipped/></testcase></testsuite>',
            '<testsuite><testcase classname="Redis" name="quota"/></testsuite>',
        ])
        self.assertEqual(result['message'], '1 passed, 1 skipped')
        self.assertEqual(result['color'], 'yellow')

    def test_classes_and_datasets_remain_distinct(self):
        report = '''<testsuite><testcase classname="A" name="test dataset 1"/>
            <testcase classname="A" name="test dataset 2"/>
            <testcase classname="B" name="test dataset 1"/></testsuite>'''
        self.assertEqual(self.render_reports([report, report])['message'], '3 passed')

    def test_missing_required_report_prevents_partial_green_badge(self):
        report = '<testsuite><testcase classname="Core" name="base"/></testsuite>'
        for missing in [None, '<testsuites/>']:
            result = self.render_reports([report, missing])
            self.assertEqual(result['message'], 'unavailable')
            self.assertEqual(result['color'], 'lightgrey')


if __name__ == '__main__':
    unittest.main()
