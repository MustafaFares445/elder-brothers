#!/usr/bin/env python3
import base64
import io
import tarfile
from pathlib import Path

root = Path(__file__).resolve().parents[1]
encoded = ''.join(path.read_text().strip() for path in sorted((root / 'tools').glob('payload.*')))
data = base64.b64decode(encoded)

with tarfile.open(fileobj=io.BytesIO(data), mode='r:gz') as archive:
    for member in archive.getmembers():
        target = (root / member.name).resolve()
        if root not in target.parents and target != root:
            raise RuntimeError(f'Unsafe archive path: {member.name}')
    archive.extractall(root)

print('Elder Brothers Laravel application extracted successfully.')
