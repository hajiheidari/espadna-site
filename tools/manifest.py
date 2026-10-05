#!/usr/bin/env python3
"""Writes public/files.json (every published file with its sha256) for the
Iranian mirror (mirror-ir/sync.php). Run by the deploy workflow, not committed.
"""
import hashlib
import json
import pathlib

PUBLIC = pathlib.Path(__file__).resolve().parent.parent / 'public'
SKIP = {'files.json', '_headers'}

files = []
for p in sorted(PUBLIC.rglob('*')):
    rel = p.relative_to(PUBLIC).as_posix()
    if p.is_file() and rel not in SKIP:
        data = p.read_bytes()
        files.append({'path': rel, 'size': len(data), 'sha256': hashlib.sha256(data).hexdigest()})
version = hashlib.sha256(''.join(f['sha256'] for f in files).encode()).hexdigest()[:12]
(PUBLIC / 'files.json').write_text(json.dumps({'version': version, 'files': files}))
print(f'public/files.json: {len(files)} files, version {version}')
