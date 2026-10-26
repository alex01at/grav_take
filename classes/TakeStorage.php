<?php
namespace Grav\Plugin\Take;

use Grav\Common\File\CompiledYamlFile;
use Grav\Common\Filesystem\Folder;
use Grav\Common\Grav;
use Grav\Common\Utils;

/**
 * Lesen/Schreiben der Feiern + Einträge als YAML-Dateien unter user/data/take/events/.
 * Kein Datenbank-Overhead nötig, ein File pro Feier.
 */
class TakeStorage
{
    protected static function dir(): string
    {
        $dir = Grav::instance()['locator']->findResource('user-data://take/events', true, true);
        if (!is_dir($dir)) {
            Folder::create($dir);
        }
        return $dir;
    }

    protected static function file(string $slug): CompiledYamlFile
    {
        return CompiledYamlFile::instance(static::dir() . '/' . $slug . '.yaml');
    }

    protected static function slugify(string $text): string
    {
        $slug = Utils::normalizeUrl($text) ?: $text;
        $slug = preg_replace('/[^a-z0-9]+/i', '-', $slug);
        $slug = trim(strtolower($slug), '-');
        return $slug !== '' ? $slug : 'feier';
    }

    /**
     * @return array<string, array> slug => Feier-Daten (inkl. 'slug')
     */
    public static function listEvents(): array
    {
        $events = [];
        foreach (glob(static::dir() . '/*.yaml') ?: [] as $path) {
            $slug = basename($path, '.yaml');
            $event = static::getEvent($slug);
            if ($event) {
                $events[$slug] = $event;
            }
        }
        uasort($events, static function ($a, $b) {
            return strcmp((string)($a['date'] ?? ''), (string)($b['date'] ?? ''));
        });
        return $events;
    }

    public static function getEvent(string $slug): ?array
    {
        $file = static::file($slug);
        if (!$file->exists()) {
            return null;
        }
        $data = (array)$file->content();
        $data['slug'] = $slug;
        $data['title'] = $data['title'] ?? '';
        $data['date'] = $data['date'] ?? '';
        $data['entries'] = $data['entries'] ?? [];
        return $data;
    }

    public static function createEvent(string $title, string $date): string
    {
        $base = static::slugify($title !== '' ? $title : $date);
        $slug = $base;
        $i = 1;
        while (static::file($slug)->exists()) {
            $slug = $base . '-' . (++$i);
        }
        $file = static::file($slug);
        $file->content([
            'title' => $title,
            'date' => $date,
            'entries' => [],
        ]);
        $file->save();
        return $slug;
    }

    public static function updateEvent(string $slug, string $title, string $date): void
    {
        $event = static::getEvent($slug);
        if (!$event) {
            return;
        }
        $file = static::file($slug);
        $file->content([
            'title' => $title,
            'date' => $date,
            'entries' => $event['entries'],
        ]);
        $file->save();
    }

    public static function deleteEvent(string $slug): void
    {
        $file = static::file($slug);
        if ($file->exists()) {
            $file->delete();
        }
    }

    /**
     * @param string[] $items
     */
    public static function addEntry(string $slug, string $name, string $email, array $items): void
    {
        $event = static::getEvent($slug);
        if (!$event) {
            return;
        }
        $items = array_values(array_filter(array_map('trim', $items), static function ($v) {
            return $v !== '';
        }));
        $event['entries'][] = [
            'name' => trim($name),
            'email' => trim($email),
            'items' => $items,
        ];
        $file = static::file($slug);
        $file->content([
            'title' => $event['title'],
            'date' => $event['date'],
            'entries' => $event['entries'],
        ]);
        $file->save();
    }

    /**
     * @param string[] $items
     */
    public static function updateEntry(string $slug, int $index, string $name, array $items): void
    {
        $event = static::getEvent($slug);
        if (!$event || !isset($event['entries'][$index])) {
            return;
        }
        $items = array_values(array_filter(array_map('trim', $items), static function ($v) {
            return $v !== '';
        }));
        $event['entries'][$index]['name'] = trim($name);
        $event['entries'][$index]['items'] = $items;
        $file = static::file($slug);
        $file->content([
            'title' => $event['title'],
            'date' => $event['date'],
            'entries' => $event['entries'],
        ]);
        $file->save();
    }

    public static function deleteEntry(string $slug, int $index): void
    {
        $event = static::getEvent($slug);
        if (!$event || !isset($event['entries'][$index])) {
            return;
        }
        unset($event['entries'][$index]);
        $event['entries'] = array_values($event['entries']);
        $file = static::file($slug);
        $file->content([
            'title' => $event['title'],
            'date' => $event['date'],
            'entries' => $event['entries'],
        ]);
        $file->save();
    }
}
