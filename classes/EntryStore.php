<?php

declare(strict_types=1);

namespace Grav\Plugin\Take;

use Grav\Common\Grav;

/**
 * Feiern (aus der Plugin-Konfiguration) und Eintragungen (eine JSON-Datei
 * pro Eintragung unter user/data/take/<event_key>/).
 */
final class EntryStore
{
    /**
     * Feiern aus der Konfiguration, normalisiert. Der Schlüssel ist ein
     * Hash aus Name+Datum, damit eine im Admin umsortierte Liste zwischen
     * Formularanzeige und Absenden nicht die falsche Feier trifft.
     */
    public static function events(): array
    {
        $events = [];
        foreach ((array) Grav::instance()['config']->get('plugins.take.events', []) as $row) {
            $name = trim((string) ($row['name'] ?? ''));
            $date = trim((string) ($row['date'] ?? ''));
            if ($name === '' || $date === '') {
                continue;
            }
            $key = self::key($name, $date);
            $events[$key] = ['key' => $key, 'name' => $name, 'date' => $date];
        }
        uasort($events, static fn ($a, $b) => strcmp($a['date'], $b['date']));

        return $events;
    }

    public static function key(string $name, string $date): string
    {
        return substr(md5($name . '|' . $date), 0, 12);
    }

    /**
     * @param string[] $items
     * @return array{entry?: array, error?: string}
     */
    public static function create(string $eventKey, string $name, string $email, array $items): array
    {
        if (!isset(self::events()[$eventKey])) {
            return ['error' => 'Diese Feier gibt es nicht (mehr).'];
        }

        $name = trim(preg_replace('/\s+/u', ' ', $name) ?? '');
        if ($name === '') {
            return ['error' => 'Bitte gib deinen Namen ein.'];
        }
        $name = mb_substr($name, 0, 100);

        $items = self::normalizeItems($items);

        $entry = [
            'id' => date('Ymd-His') . '-' . bin2hex(random_bytes(3)),
            'date' => date('c'),
            'name' => $name,
            'email' => trim($email),
            'items' => $items,
        ];

        $dir = self::dir($eventKey);
        if ($dir === null) {
            return ['error' => 'Eintragung konnte nicht gespeichert werden.'];
        }
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            return ['error' => 'Eintragung konnte nicht gespeichert werden.'];
        }
        $json = json_encode($entry, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        if (file_put_contents($dir . '/' . $entry['id'] . '.json', $json, LOCK_EX) === false) {
            return ['error' => 'Eintragung konnte nicht gespeichert werden.'];
        }

        return ['entry' => $entry];
    }

    /** Alle Feiern mit ihren Eintragungen, für die Admin-Übersicht. */
    public static function allGrouped(): array
    {
        $result = [];
        foreach (self::events() as $key => $event) {
            $result[] = $event + ['entries' => self::entries($key)];
        }

        return $result;
    }

    public static function entries(string $eventKey): array
    {
        $dir = self::dir($eventKey);
        if ($dir === null) {
            return [];
        }
        $files = glob($dir . '/*.json') ?: [];
        sort($files);

        $entries = [];
        foreach ($files as $file) {
            $data = json_decode((string) file_get_contents($file), true);
            if (is_array($data) && isset($data['id'])) {
                $entries[] = $data;
            }
        }

        return $entries;
    }

    /**
     * @param string[] $items
     */
    public static function update(string $eventKey, string $id, string $name, array $items): bool
    {
        $file = self::entryFile($eventKey, $id);
        if ($file === null || !is_file($file)) {
            return false;
        }
        $data = json_decode((string) file_get_contents($file), true);
        if (!is_array($data)) {
            return false;
        }
        $data['name'] = mb_substr(trim($name), 0, 100);
        $data['items'] = self::normalizeItems($items);

        return file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX) !== false;
    }

    public static function delete(string $eventKey, string $id): bool
    {
        $file = self::entryFile($eventKey, $id);

        return $file !== null && is_file($file) && unlink($file);
    }

    /**
     * @param string[] $items
     * @return string[]
     */
    private static function normalizeItems(array $items): array
    {
        return array_values(array_filter(array_map(
            static fn ($v) => mb_substr(trim((string) $v), 0, 200),
            $items
        ), static fn ($v) => $v !== ''));
    }

    private static function entryFile(string $eventKey, string $id): ?string
    {
        if (!preg_match('/^[0-9]{8}-[0-9]{6}-[0-9a-f]{6}$/', $id)) {
            return null;
        }
        $dir = self::dir($eventKey);

        return $dir === null ? null : $dir . '/' . $id . '.json';
    }

    private static function dir(string $eventKey): ?string
    {
        if (!preg_match('/^[0-9a-f]{12}$/', $eventKey)) {
            return null;
        }
        $locator = Grav::instance()['locator'];

        return $locator->findResource('user://data', true, true) . '/take/' . $eventKey;
    }
}
