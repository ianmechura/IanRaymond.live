#!/usr/bin/env python3
"""Build the installable notification plugin from its editable source."""
from pathlib import Path
from zipfile import ZipFile, ZIP_DEFLATED
root = Path(__file__).resolve().parents[1]
source = root / 'src'
target = root / 'release' / 'ian-raymond-notifications.zip'
target.parent.mkdir(parents=True, exist_ok=True)
with ZipFile(target, 'w', ZIP_DEFLATED) as archive:
    for path in sorted((source / 'ian-raymond-notifications').rglob('*')):
        if path.is_file():
            archive.write(path, path.relative_to(source))
print(target)
