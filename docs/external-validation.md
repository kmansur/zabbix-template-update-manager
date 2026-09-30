# Independent external validation

ZTUM distinguishes repository automation from independent field evidence. A CI run owned by the project is not counted as an external review.

## Reproducible handoff

On the system under review, run:

```bash
bash tools/ztum-validation-bundle.sh \
  --module-dir /usr/share/zabbix/modules/zabbix-template-update-manager \
  --output /tmp/ztum-validation \
  --browser "Chromium <version>" \
  --theme "dark"
```

Review the generated files before sharing them. The helper is read-only and deliberately excludes credentials, database connection values, tokens and template configuration contents.

The reviewer should verify the exact immutable tag/commit and archive checksum, execute the applicable Zabbix 7.x or 8.x matrix, and return the completed checklist plus sanitized logs/screenshots.

## Independence criterion

Evidence is considered external only when the person or organization executing and reviewing the matrix is not the author of the change being validated. Project CI, the original developer, and an AI review can improve quality but do not satisfy this criterion.

## Stop condition

If a controlled write is uncertain, post-validation is not proven, a signature/tamper check fails unexpectedly, or an entry remains `running` after interruption, stop. Do not retry automatically. Preserve the evidence bundle and inspect the current Zabbix state first.
