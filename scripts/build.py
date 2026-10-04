#!/usr/bin/env python3
"""Build a self-contained offline Unraid PLG; no remote release URL required."""
from pathlib import Path
import base64, hashlib, io, tarfile
VERSION = '1.0.4'
AUTHOR = 'mizhimu'
root = Path(__file__).resolve().parents[1]
(root/'dist').mkdir(exist_ok=True)
archive = io.BytesIO()
with tarfile.open(fileobj=archive, mode='w:gz') as tar:
    tar.add(root/'src/port-manager', arcname='port-manager')
payload = archive.getvalue()
(root/f'dist/port-manager-{VERSION}.tar.gz').write_bytes(payload)
sha = hashlib.sha256(payload).hexdigest()
encoded = base64.b64encode(payload).decode()
plg = f'''<?xml version="1.0" standalone="yes"?>
<PLUGIN name="port-manager" author="{AUTHOR}" version="{VERSION}" min="6.12.0" launch="PortManager" icon="exchange" support="https://github.com/mizhimu/unraid-port-manager" pluginURL="https://raw.githubusercontent.com/mizhimu/unraid-port-manager/main/port-manager.plg">
<CHANGES><![CDATA[
### 1.0.4
- Enable update checks and one-click updates through the Unraid Plugins page.
- Add the standard Support Thread link pointing to the project GitHub repository.
- Remove the expandable feature description from the plugin list; retain a short introduction.
- Include the 1.0.3 fixes: clear stale recommendations on refresh, exclude unpublished macvlan/ipvlan port configurations, and preserve all shared listener processes.
]]></CHANGES>
<FILE Name="/boot/config/plugins/port-manager/payload-{VERSION}-{sha[:12]}.b64">
<INLINE><![CDATA[{encoded}]]></INLINE>
</FILE>
<FILE Run="/bin/bash">
<INLINE><![CDATA[
set -e
base64 -d /boot/config/plugins/port-manager/payload-{VERSION}-{sha[:12]}.b64 > /tmp/port-manager-package.tar.gz
echo '{sha}  /tmp/port-manager-package.tar.gz' | sha256sum -c -
mkdir -p /usr/local/emhttp/plugins
tar -xzf /tmp/port-manager-package.tar.gz -C /usr/local/emhttp/plugins
chmod -R u=rwX,go=rX /usr/local/emhttp/plugins/port-manager
rm -f /tmp/port-manager-package.tar.gz
echo 'Port Manager installed: Tools > Port Manager'
]]></INLINE>
</FILE>
<FILE Run="/bin/bash" Method="remove">
<INLINE><![CDATA[
rm -rf /usr/local/emhttp/plugins/port-manager /boot/config/plugins/port-manager
]]></INLINE>
</FILE>
</PLUGIN>
'''
(root/'port-manager.plg').write_text(plg)
(root/'dist/port-manager.plg').write_text(plg)
print('Built self-contained PLG and archive:', sha)
