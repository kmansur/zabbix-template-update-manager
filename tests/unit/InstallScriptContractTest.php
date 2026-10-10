<?php

$source = (string) file_get_contents(dirname(__DIR__, 2).'/install.sh');
$requirements = [
    'source-relative install' => 'SOURCE_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd -P)"',
    'automatic modules discovery' => 'valid_candidates=()',
    'automatic PHP-FPM detection' => 'PHP_USER="${users[0]}"',
    'readonly existing-install report' => 'CHECK: installed directory detected; no files were changed.',
    'readonly new-install report' => 'CHECK: READY for a new installation. Runtime directories will be secured during install; no files changed.',
    'existing-install overwrite protection' => 'Refusing overwrite; use --upgrade only after validation.',
    'root-owned staged module' => 'chown -R root:root "$STAGE"',
    'private runtime setup' => 'prepare_runtime',
    'safe runtime-check option guard' => 'if ((RUNTIME_CHECK)) && { ((DRY_RUN || UPGRADE)) || [[ -n "$ROLLBACK" ]]; }; then',
    'assets included' => 'for d in actions assets src views;',
    'registered update JavaScript verification' => 'assets/js/ztum-update-batch.js',
    'registered install JavaScript verification' => 'assets/js/ztum-install-batch.js',
    'backup id assigned before use' => 'folder="$BACKUP_BASE/$id"',
    'cleanup old initialized outside local scope' => '  old=""',
    'upgrade flag' => '--upgrade) UPGRADE=1',
    'rollback flag' => '--rollback) (($# >= 2))',
    'version-gated upgrade' => 'version_compare($argv[1],$argv[2], ">")',
    'private backup directory' => '/var/backups/zabbix-template-update-manager',
    'upgrade lock' => 'flock -n 9',
    'backup integrity validation' => 'verify_tree "$folder/module" "$expected"',
    'rollback integrity preflight logging' => 'Rollback preflight: SHA-256 inventory verified',
    'fail closed on corrupt backup' => 'Backup integrity failure (missing, changed or extra files); rollback aborted before changes',
    'reject empty checksum inventory' => 'Empty backup checksum manifest; rollback aborted before changes',
    'code only staging' => 'prepare_stage "$SOURCE_DIR" "$stage"',
    'explicit rollback staging' => 'prepare_stage "$saved" "$stage"',
    'rollback does not mutate runtime state' => 'Private runtime state untouched.',
];
foreach ($requirements as $name => $needle) {
    if (!str_contains($source, $needle)) {
        fwrite(STDERR, "FAIL: installer contract: $name\n");
        exit(1);
    }
}
echo "Installer default, safe-check and minimal runtime file contracts passed.\n";
