#!/usr/bin/env python3
"""Validate the published self-contained PLG without executing install scripts."""
import base64
import hashlib
import io
from pathlib import Path
import subprocess
import tarfile
import xml.etree.ElementTree as ET

root = Path(__file__).resolve().parents[1]
plugin = ET.parse(root / 'port-manager.plg').getroot()
assert plugin.attrib['support'] == 'https://github.com/mizhimu/unraid-port-manager'
assert plugin.attrib['pluginURL'] == 'https://raw.githubusercontent.com/mizhimu/unraid-port-manager/main/port-manager.plg'
assert plugin.attrib['name'] == 'port-manager'
payload = base64.b64decode(plugin.find('FILE/INLINE').text)
assert hashlib.sha256(payload).hexdigest() in plugin.findall('FILE')[1].find('INLINE').text
with tarfile.open(fileobj=io.BytesIO(payload)) as archive:
    for member in archive.getmembers():
        if member.isfile():
            assert (root / 'src' / member.name).read_bytes() == archive.extractfile(member).read(), member.name
    description = archive.extractfile('port-manager/README.md').read().decode()
    assert '<details>' not in description and '<summary>' not in description
for entry in plugin.findall('FILE'):
    if entry.get('Run'):
        subprocess.run(['bash', '-n'], input=entry.find('INLINE').text, text=True, check=True)
assert (root / 'dist/port-manager.plg').read_bytes() == (root / 'port-manager.plg').read_bytes()
print('PASS metadata, update/support links, description, SHA256, source equality and shell syntax')
