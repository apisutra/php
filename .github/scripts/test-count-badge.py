"""Формирование бейджа Shields.io по уникальным тестам из JUnit-отчётов."""

import json
from pathlib import Path
import sys
import xml.etree.ElementTree as ET


def build_badge(report: Path, outcome: str, *additional_reports: Path) -> dict:
    badge = {'schemaVersion': 1, 'label': 'tests', 'message': 'unavailable', 'color': 'lightgrey'}
    states = {}
    for report_index, source in enumerate((report, *additional_reports)):
        if not source.is_file():
            return badge
        # Pest может обрезать имя testsuite посреди UTF-8 символа.
        root = ET.fromstring(source.read_text(encoding='utf-8', errors='replace'))
        cases = list(root.iter('testcase'))
        if not cases:
            return badge
        for index, case in enumerate(cases):
            # Абсолютный file различается на хосте и в контейнере. Dataset входит в name.
            identity = (case.get('classname') or case.get('class', ''), case.get('name'))
            if not identity[1]:
                identity = (report_index, index)
            # Ошибка важнее успеха; успешный запуск покрывает пропуск в другом окружении.
            state = 3 if case.find('failure') is not None or case.find('error') is not None else (
                1 if case.find('skipped') is not None else 2
            )
            states[identity] = max(states.get(identity, 0), state)

    failed = sum(state == 3 for state in states.values())
    skipped = sum(state == 1 for state in states.values())
    passed = sum(state == 2 for state in states.values())
    parts = [f'{passed} passed']
    if failed:
        parts.append(f'{failed} failed')
    if skipped:
        parts.append(f'{skipped} skipped')
    if outcome != 'success' and not failed:
        parts.append('run failed')

    badge['message'] = ', '.join(parts)
    badge['color'] = 'red' if failed or outcome != 'success' else ('yellow' if skipped else 'brightgreen')
    return badge


if __name__ == '__main__':
    report, destination, outcome, *additional_reports = sys.argv[1:]
    badge = build_badge(Path(report), outcome, *(Path(path) for path in additional_reports))
    Path(destination).write_text(json.dumps(badge, indent=2) + '\n')
