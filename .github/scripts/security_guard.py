#!/usr/bin/env python3
"""Scan tracked files and Git history without printing matched private values."""
from __future__ import annotations

import argparse
from dataclasses import asdict, dataclass
from datetime import datetime, timezone
import hashlib
import json
import os
from pathlib import Path
import re
import subprocess
import sys
import unicodedata

MAX_SCAN_BYTES = 20 * 1024 * 1024
ALLOWED_ENV_NAMES = {'.env.example', '.env.sample', '.env.template', '.env.dist'}
FORBIDDEN_BASENAMES = {
    '.env', '.pypirc', '.netrc', 'auth.json', 'credentials.json',
    'service-account.json', 'id_rsa', 'id_ed25519',
}
FORBIDDEN_SUFFIXES = {'.pem', '.key', '.p12', '.pfx', '.jks', '.keystore', '.tfstate'}
FORBIDDEN_IDENTITY_HASHES = {
    '01e76a28977874f8b72265d0d39fa47c4105083556013f84ded1dad7798d01f7',
    'ccb810ff1aea7ea61ea5c412bf549ca31b9d217d34357893d0ed97a54303b666',
    'ec29e4a50ab3326b494e6126f3299ed436b1c24d3c508e364ee48345fc6c7a0b',
    'a6710e26418bd4c6d2ee839605cd40c313ac3b79e599c1be31aa2bd711c665e3',
}
PRIVATE_KEY_MARKERS = tuple(
    b'-----BEGIN ' + value
    for value in (
        b'PRIVATE KEY-----',
        b'ENCRYPTED PRIVATE KEY-----',
        b'RSA PRIVATE KEY-----',
        b'OPENSSH PRIVATE KEY-----',
        b'EC PRIVATE KEY-----',
    )
)
SELF_PATH = '.github/scripts/security_guard.py'
APPROVED_HISTORY_PATH = Path('.security/approved-historical-identity-findings.json')
APPROVED_HISTORY_CATEGORY = 'forbidden personal identifier in historical content'
APPROVED_HISTORY_LOCATION_RE = re.compile(r'^blob:[0-9a-f]{12}:.+:\d+$')
TOKEN_RE = re.compile(r'[a-z0-9]+')
ASCII_TOKEN_RE = re.compile(rb'[A-Za-z0-9]{3,}')


@dataclass(frozen=True)
class Finding:
    scope: str
    location: str
    category: str


def git(args: list[str], data: bytes | None = None) -> bytes:
    return subprocess.run(
        ['git', *args],
        input=data,
        check=True,
        stdout=subprocess.PIPE,
        stderr=subprocess.PIPE,
    ).stdout


def tokens(text: str) -> list[str]:
    normalized = unicodedata.normalize('NFKD', text)
    ascii_text = normalized.encode('ascii', 'ignore').decode().lower()
    return TOKEN_RE.findall(ascii_text)


def identity_match(items: list[str]) -> bool:
    candidates = list(items)
    candidates += [''.join(items[i:i + 2]) for i in range(max(0, len(items) - 1))]
    candidates += [''.join(items[i:i + 3]) for i in range(max(0, len(items) - 2))]
    return any(
        hashlib.sha256(candidate.encode()).hexdigest() in FORBIDDEN_IDENTITY_HASHES
        for candidate in candidates
    )


def path_categories(path: Path) -> list[str]:
    name = path.name.lower()
    categories: list[str] = []
    if name.startswith('.env') and name not in ALLOWED_ENV_NAMES:
        categories.append('tracked environment file')
    if name in FORBIDDEN_BASENAMES:
        categories.append('tracked credential file')
    if path.suffix.lower() in FORBIDDEN_SUFFIXES:
        categories.append('tracked key or credential container')
    return categories


def content_categories(data: bytes, check_keys: bool = True) -> list[tuple[int | None, str]]:
    categories: list[tuple[int | None, str]] = []
    if check_keys and any(marker in data for marker in PRIVATE_KEY_MARKERS):
        categories.append((None, 'private-key material marker'))
    if b'\0' in data:
        binary_tokens = [
            value.decode('ascii', 'ignore').lower()
            for value in ASCII_TOKEN_RE.findall(data)
        ]
        if identity_match(binary_tokens):
            categories.append((None, 'forbidden personal identifier in binary data'))
        return categories
    for number, line in enumerate(data.decode('utf-8', 'replace').splitlines(), 1):
        if identity_match(tokens(line)):
            categories.append((number, 'forbidden personal identifier'))
    return categories


def scan_tree() -> list[Finding]:
    findings: list[Finding] = []
    paths = [
        Path(os.fsdecode(value))
        for value in git(['ls-files', '-z']).split(b'\0')
        if value
    ]
    for path in paths:
        findings += [
            Finding('tracked-tree', str(path), category)
            for category in path_categories(path)
        ]
        try:
            if path.stat().st_size > MAX_SCAN_BYTES:
                continue
            data = path.read_bytes()
        except OSError:
            findings.append(Finding('tracked-tree', str(path), 'unreadable tracked file'))
            continue
        for line, category in content_categories(data, path.as_posix() != SELF_PATH):
            location = f'{path}:{line}' if line else str(path)
            findings.append(Finding('tracked-tree', location, category))
    return findings


def scan_metadata() -> list[Finding]:
    output = git([
        'log', '--all', '--format=%H%x1f%an%x1f%ae%x1f%cn%x1f%ce%x1f%B%x1e',
    ]).decode('utf-8', 'replace')
    findings: list[Finding] = []
    names = ('author name', 'author email', 'committer name', 'committer email', 'message')
    for record in output.split('\x1e'):
        fields = record.strip('\n').split('\x1f', 5)
        if len(fields) != 6:
            continue
        sha, *values = fields
        for field, value in zip(names, values):
            if identity_match(tokens(value)):
                findings.append(Finding(
                    'git-history',
                    f'commit:{sha[:12]}',
                    f'forbidden personal identifier in {field}',
                ))
    return findings


def scan_blobs() -> list[Finding]:
    objects: dict[str, str] = {}
    for line in git(['rev-list', '--objects', '--all']).decode('utf-8', 'replace').splitlines():
        oid, _, path = line.partition(' ')
        objects.setdefault(oid, path)
    checks = git(
        ['cat-file', '--batch-check=%(objectname) %(objecttype) %(objectsize)'],
        ('\n'.join(objects) + '\n').encode(),
    ).decode()
    eligible: list[str] = []
    for line in checks.splitlines():
        parts = line.split()
        if len(parts) == 3 and parts[1] == 'blob' and int(parts[2]) <= MAX_SCAN_BYTES:
            eligible.append(parts[0])

    process = subprocess.Popen(
        ['git', 'cat-file', '--batch'],
        stdin=subprocess.PIPE,
        stdout=subprocess.PIPE,
        stderr=subprocess.DEVNULL,
    )
    assert process.stdin and process.stdout
    findings: list[Finding] = []
    for oid in eligible:
        process.stdin.write((oid + '\n').encode())
        process.stdin.flush()
        header = process.stdout.readline().decode('ascii', 'replace').split()
        if len(header) != 3:
            continue
        data = process.stdout.read(int(header[2]))
        process.stdout.read(1)
        path = objects.get(oid) or '<unknown-path>'
        for line, category in content_categories(data, path != SELF_PATH):
            location = f'blob:{oid[:12]}:{path}' + (f':{line}' if line else '')
            findings.append(Finding(
                'git-history',
                location,
                category.replace(
                    'forbidden personal identifier',
                    'forbidden personal identifier in historical content',
                ),
            ))
    process.stdin.close()
    process.wait(timeout=30)
    return findings


def load_approved_history_findings(path: Path = APPROVED_HISTORY_PATH) -> set[Finding]:
    if not path.is_file():
        return set()
    data = json.loads(path.read_text(encoding='utf-8'))
    if data.get('schema_version') != 1 or not isinstance(data.get('findings'), list):
        raise ValueError('invalid approved historical findings schema')

    approved: set[Finding] = set()
    for item in data['findings']:
        if not isinstance(item, dict) or set(item) != {'location', 'category'}:
            raise ValueError('invalid approved historical finding entry')
        location = item['location']
        category = item['category']
        if not isinstance(location, str) or not APPROVED_HISTORY_LOCATION_RE.fullmatch(location):
            raise ValueError('invalid approved historical finding location')
        if category != APPROVED_HISTORY_CATEGORY:
            raise ValueError('invalid approved historical finding category')
        finding = Finding('git-history', location, category)
        if finding in approved:
            raise ValueError('duplicate approved historical finding entry')
        approved.add(finding)
    return approved


def partition_findings(
    findings: list[Finding], approved: set[Finding]
) -> tuple[list[Finding], list[Finding], list[Finding]]:
    finding_set = set(findings)
    matched = sorted(finding_set & approved, key=lambda item: (item.location, item.category))
    active = sorted(finding_set - approved, key=lambda item: (item.scope, item.location, item.category))
    stale = sorted(approved - finding_set, key=lambda item: (item.location, item.category))
    return active, matched, stale


def write_report(
    path: Path,
    *,
    history_enabled: bool,
    active: list[Finding],
    approved: list[Finding],
    stale: list[Finding],
) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(json.dumps({
        'schema_version': 2,
        'generated_at_utc': datetime.now(timezone.utc).isoformat(),
        'history_enabled': history_enabled,
        'safe_output': True,
        'matched_values_included': False,
        'status': 'findings' if active or stale else 'passed',
        'finding_count': len(active),
        'findings': [asdict(item) for item in active],
        'approved_history_count': len(approved),
        'approved_history_findings': [asdict(item) for item in approved],
        'stale_approval_count': len(stale),
        'stale_approvals': [asdict(item) for item in stale],
    }, indent=2, ensure_ascii=False) + '\n', encoding='utf-8')


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument('--history', action='store_true')
    parser.add_argument('--report', type=Path)
    args = parser.parse_args()

    try:
        approved = load_approved_history_findings()
    except (OSError, json.JSONDecodeError, ValueError):
        print('Approved historical findings configuration is invalid; refusing to continue.')
        return 1

    findings = scan_tree()
    if args.history:
        findings += scan_metadata() + scan_blobs()
    active, matched, stale = partition_findings(findings, approved if args.history else set())

    if args.report:
        write_report(
            args.report,
            history_enabled=args.history,
            active=active,
            approved=matched,
            stale=stale,
        )

    if stale:
        print('Approved historical findings no longer match the scanned history; review required.')
    if active:
        for finding in active:
            print(f'- {finding.location}: {finding.category} [{finding.scope}]')
    if active or stale:
        print('No matched value was printed. Review the sanitized report and rotate any exposed secret.')
        return 1

    if matched:
        print(f'Security guard passed with {len(matched)} approved historical findings.')
    else:
        print('Security guard passed without exposing matched values.')
    return 0


if __name__ == '__main__':
    sys.exit(main())
