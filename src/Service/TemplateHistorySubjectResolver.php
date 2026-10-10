<?php

namespace Modules\ZabbixTemplateUpdateManager\Service;

/**
 * Read-only, display-only resolution of historical subjects.
 * No stored history is rewritten. Missing or deleted templates retain their
 * immutable subject identifiers so audit evidence remains unambiguous.
 */
final class TemplateHistorySubjectResolver {
    public static function resolve(array $entries, array $templates): array {
        $byId = [];
        $byUuid = [];
        foreach ($templates as $template) {
            if (!is_array($template)) {
                continue;
            }
            $id = trim((string) ($template['templateid'] ?? ''));
            $uuid = strtolower(str_replace('-', '', trim((string) ($template['uuid'] ?? ''))));
            $name = trim((string) ($template['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            if (ctype_digit($id) && (int) $id > 0) {
                $byId[$id] = $name;
            }
            if (preg_match('/^[a-f0-9]{32}$/', $uuid)) {
                $byUuid[$uuid] = $name;
            }
        }

        foreach ($entries as &$entry) {
            if (!is_array($entry)) {
                continue;
            }
            $subject = (string) ($entry['subject'] ?? '');
            $name = null;
            if (preg_match('/^template-([0-9]+)$/', $subject, $match)) {
                $name = $byId[$match[1]] ?? null;
            }
            elseif (preg_match('/^uuid-([a-fA-F0-9-]+)$/', $subject, $match)) {
                $normalized = strtolower(str_replace('-', '', $match[1]));
                if (preg_match('/^[a-f0-9]{32}$/', $normalized)) {
                    $name = $byUuid[$normalized] ?? null;
                }
            }
            $entry['display_subject'] = $name !== null ? $name : $subject;
        }
        unset($entry);
        return $entries;
    }
}
