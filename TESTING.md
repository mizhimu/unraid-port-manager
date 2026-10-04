# Testing

## Automated checks

Run the commands in README.md. The existing 37 core assertions cover parsing, ownership, reservations, overlap, grouping, service evidence, complete-range recommendations, independent-IP network exclusions and shared process preservation.

Package validation checks XML metadata, SHA256, archive/source equality, installed description and shell syntax. These checks do not execute Unraid installation scripts.

## Current evidence

- 1.0.3: PHP 7.4 syntax parsing and PHP 8.4 WASM core tests passed locally. Mocked scan execution confirmed complete and incomplete refreshes clear stale candidates.
- Earlier versions were tested on Unraid 7.3.2 / PHP 8.4: actual scan, authenticated search/filter/recommendation, dashboard and unauthenticated redirects passed.
- 1.0.4: new support metadata and removal of expanded plugin description are validated in the package. Target NAS installation and rendering remain unverified.
- Reboot restoration and uninstall have not been verified on a target NAS.

## 1.1.0 evidence and container acceptance

- Existing scanner: 37 assertions passed on the NAS PHP runtime; strict recommendation: 16 assertions passed. PHP syntax checks passed.
- New recommendation code ran against live Unraid 7.3.2 data over SSH without installing the plugin: complete scan, ascending candidates starting at 5002, 692 candidates in that snapshot.
- Playwright fixture using native 7.3.2 field IDs passed: popup lifecycle, form exclusions, click-to-fill with target preservation, batches, manual exclusion reasons, partial-scan failure, network changes, cancelled stale requests and reopening. Preview is fixture data, not the NAS page.
- Native `Buttons` hook and popup structure were inspected on the NAS; the actual page loader enabled the hook for AddContainer/UpdateContainer and disabled it for Dashboard/Docker. All three API actions executed successfully against live data. 1.1.0 and 1.1.1 were subsequently installed successfully; installed API checks passed. Native authenticated browser rendering is not independently verified.

For browser fixture checks, make Playwright available and run `node tests/container.cjs`; use `PM_BROWSER_CHANNEL=chrome` to use installed Chrome. The test does not need a NAS connection.

After installing 1.1.0 on a test NAS:

1. Open Add Container, add a configuration, select Port: six compact candidates in one row beneath Host Port; Path/Variable/Label/Device show no helper.
2. Click a candidate: only Host Port changes. Add it, reopen, confirm this unsaved form port is excluded.
3. Try a stopped container port, system reservation, common service port and free port; confirm distinct reasons. Repeat in edit popup.
4. Switch bridge/host/macvlan/ipvlan networks and reopen; recommendations only apply to host publishing on bridge networks.
5. Refresh after port occupancy changes; simulate incomplete collection and verify no stale candidate remains. Close a pending request and reopen.
6. Check dark/light themes and narrow screens; ensure popup fits and all native buttons work. Confirm no extra navigation button appears.
7. Configure custom reservations and VM display ranges; verify exclusions. Restore test reservations afterwards.

## Unraid acceptance checklist

1. Install the PLG and confirm Tools → 端口状态 and the dashboard card.
2. Check the Plugins list: brief introduction, no Features and scope disclosure, and Support Thread linking to the GitHub repository.
3. Compare results with `ss -H -lntup` and `docker inspect` for bridge, host, stopped, random-published and independent-IP containers.
4. Test macvlan/ipvlan containers with retained port configuration. Confirm unused host ports are not falsely reserved. Verify custom bridge bindings remain present.
5. Recommend a port, occupy it, refresh and confirm old candidates disappear.
6. Confirm shared listener details retain all collected process IDs.
7. Verify partial collection disables recommendations, and logged-out page/API access redirects to authentication.
8. Check search, filters, clipboard, dashboard refresh and narrow-screen layout.
9. Verify reboot restoration and uninstall on a test NAS.

Do not treat fixture, syntax or archive checks as evidence of target-machine runtime acceptance.

## 1.1.1 checks

- Settings tests cover default enablement, existing manually created lists, atomic persistence, comments/ranges, clearing and preserving configuration on invalid saves.
- Container browser fixture checks six candidates on one row at 24 px height, the settings link and disabled recommendation behavior.
- `node tests/settings.cjs` loads the real full-page HTML/JS with mocked endpoints and checks load, CSRF form body, save, reload and server validation without losing edited text.
- Settings are written only by authenticated WebGUI POST requests protected by Unraid's PHP prepend CSRF validation. Reads and port scans remain read-only.

## 2026.10.04 checks

- Browser fixtures verified a three-card desktop row at 1180 px, stacked layout at 600 px without page overflow, and generated new README illustration images.
- Package tests require the PLG CHANGES block to match the complete CHANGELOG.md, including every historical release.
- Date-based metadata supports upgrade from earlier 1.x versions; installed NAS version and API checks are reported separately from mocked browser evidence.

- Installed 2026.10.04 on Unraid 7.3.2 successfully; metadata showed the date version, historical CHANGES entries were present, and the installed recommendation API returned a complete snapshot.
