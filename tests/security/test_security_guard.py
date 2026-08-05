from __future__ import annotations

import importlib.util
import json
from pathlib import Path
import sys
import tempfile
import unittest

SCRIPT = Path(__file__).parents[2] / '.github' / 'scripts' / 'security_guard.py'
SPEC = importlib.util.spec_from_file_location('security_guard', SCRIPT)
assert SPEC and SPEC.loader
security_guard = importlib.util.module_from_spec(SPEC)
sys.modules[SPEC.name] = security_guard
SPEC.loader.exec_module(security_guard)

Finding = security_guard.Finding
CATEGORY = security_guard.APPROVED_HISTORY_CATEGORY


class ApprovedHistoryTests(unittest.TestCase):
    def write_manifest(self, findings: list[dict[str, str]]) -> Path:
        directory = tempfile.TemporaryDirectory()
        self.addCleanup(directory.cleanup)
        path = Path(directory.name) / 'approved.json'
        path.write_text(
            json.dumps({'schema_version': 1, 'findings': findings}),
            encoding='utf-8',
        )
        return path

    def test_exact_historical_finding_is_approved(self) -> None:
        finding = Finding('git-history', 'blob:0123456789ab:readme.txt:2', CATEGORY)
        path = self.write_manifest([{'location': finding.location, 'category': CATEGORY}])
        approved = security_guard.load_approved_history_findings(path)
        active, matched, stale = security_guard.partition_findings([finding], approved)
        self.assertEqual([], active)
        self.assertEqual([finding], matched)
        self.assertEqual([], stale)

    def test_current_tree_finding_cannot_be_approved(self) -> None:
        path = self.write_manifest([{
            'location': 'readme.txt:2',
            'category': 'forbidden personal identifier',
        }])
        with self.assertRaises(ValueError):
            security_guard.load_approved_history_findings(path)

    def test_unknown_historical_finding_remains_blocking(self) -> None:
        approved_finding = Finding(
            'git-history',
            'blob:0123456789ab:readme.txt:2',
            CATEGORY,
        )
        unknown_finding = Finding(
            'git-history',
            'blob:abcdef012345:readme.txt:2',
            CATEGORY,
        )
        active, matched, stale = security_guard.partition_findings(
            [approved_finding, unknown_finding],
            {approved_finding},
        )
        self.assertEqual([unknown_finding], active)
        self.assertEqual([approved_finding], matched)
        self.assertEqual([], stale)

    def test_stale_approval_is_reported(self) -> None:
        approved_finding = Finding(
            'git-history',
            'blob:0123456789ab:readme.txt:2',
            CATEGORY,
        )
        active, matched, stale = security_guard.partition_findings([], {approved_finding})
        self.assertEqual([], active)
        self.assertEqual([], matched)
        self.assertEqual([approved_finding], stale)

    def test_duplicate_approval_is_rejected(self) -> None:
        entry = {
            'location': 'blob:0123456789ab:readme.txt:2',
            'category': CATEGORY,
        }
        path = self.write_manifest([entry, entry])
        with self.assertRaises(ValueError):
            security_guard.load_approved_history_findings(path)


if __name__ == '__main__':
    unittest.main()
