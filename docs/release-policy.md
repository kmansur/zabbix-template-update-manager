# Release policy

Template Update Manager separates development commits, field-test betas and stable releases.

## Development

`main` is the integration branch. Multiple commits may land without incrementing the public field-test version for every internal change.

## Field-test beta

A new `0.x.y-beta.N` snapshot should group a meaningful set of changes and is published only after automated safety/quality gates are green, documentation is synchronized and the relevant laboratory regression has been exercised. Publicly distributed snapshots use a formal Git tag/GitHub prerelease; permanent per-beta `release/<version>` branches are intentionally not kept.

The project should avoid using beta numbers as per-commit build identifiers.

## Git tags and GitHub Releases

Formal tags use `v0.1.0-beta.40` / `v1.0.0`.

The release workflow verifies that the tag matches `VERSION`, reruns safety/quality checks, builds `.tar.gz` and `.zip` assets, generates `SHA256SUMS` and creates a GitHub Release. Prerelease versions are published as prereleases.

## Stable release

A stable 1.0 requires the documented Zabbix 7.x and 8.x field matrix, release/security gates, a selected project license, no unresolved high-severity safety issue and a documented compatibility statement.

Tags/releases are distribution artifacts; they do not replace field validation.
