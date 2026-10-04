# Testing

## Automated checks

Run the commands in README.md. The 37 core assertions cover parsing, ownership, reservations, overlap, grouping, service evidence, complete-range recommendations, independent-IP network exclusions and shared process preservation.

Package validation checks XML metadata, SHA256, archive/source equality, installed description and shell syntax. These checks do not execute Unraid installation scripts.

## Current evidence

- 1.0.3: PHP 7.4 syntax parsing and PHP 8.4 WASM core tests passed locally. Mocked scan execution confirmed complete and incomplete refreshes clear stale candidates.
- Earlier versions were tested on Unraid 7.3.2 / PHP 8.4: actual scan, authenticated search/filter/recommendation, dashboard and unauthenticated redirects passed.
- 1.0.4: new support metadata and removal of expanded plugin description are validated in the package. Target NAS installation and rendering remain unverified.
- Reboot restoration and uninstall have not been verified on a target NAS.

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
