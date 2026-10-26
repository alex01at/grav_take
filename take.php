<?php
namespace Grav\Plugin;

use Grav\Common\Plugin;
use Grav\Common\Security;
use Grav\Plugin\Take\TakeStorage;
use RocketTheme\Toolbox\Event\Event;

require_once __DIR__ . '/classes/TakeStorage.php';

class TakePlugin extends Plugin
{
    public static function getSubscribedEvents(): array
    {
        return [
            'onPluginsInitialized' => ['onPluginsInitialized', 0],
        ];
    }

    public function onPluginsInitialized(): void
    {
        if ($this->isAdmin()) {
            $this->enable([
                'onAdminMenu' => ['onAdminMenu', 0],
                'onTwigTemplatePaths' => ['onAdminTwigTemplatePaths', 0],
                'onPagesInitialized' => ['onAdminPagesInitialized', 0],
                'onTwigSiteVariables' => ['onAdminTwigSiteVariables', 0],
            ]);
            return;
        }

        $this->enable([
            'onTwigTemplatePaths' => ['onTwigTemplatePaths', 0],
            'onPagesInitialized' => ['onPagesInitialized', 0],
            'onShortcodeHandlers' => ['onShortcodeHandlers', 0],
            'onAssetsInitialized' => ['onAssetsInitialized', 0],
        ]);
    }

    public function onAssetsInitialized(): void
    {
        $this->grav['assets']->addCss('plugin://take/assets/take.css');
        $this->grav['assets']->addJs('plugin://take/assets/take.js', ['loading' => 'defer']);
    }

    public function onTwigTemplatePaths(): void
    {
        $this->grav['twig']->twig_paths[] = __DIR__ . '/templates';
    }

    public function onAdminTwigTemplatePaths(Event $event): void
    {
        $this->grav['twig']->twig_paths[] = __DIR__ . '/admin/templates';
    }

    public function onAdminTwigSiteVariables(): void
    {
        $uri = $this->grav['uri'];
        if (rtrim($uri->path(), '/') !== '/admin/take') {
            return;
        }
        $this->grav['twig']->twig_vars['take_events'] = TakeStorage::listEvents();
    }

    public function onAdminMenu(): void
    {
        $this->grav['twig']->plugins_hooked_nav['PLUGIN_TAKE.MENU_LABEL'] = [
            'route' => 'take',
            'icon' => 'fa-glass-cheers',
        ];
    }

    /**
     * CRUD für Feiern & Einträge in der Administration.
     */
    public function onAdminPagesInitialized(): void
    {
        $uri = $this->grav['uri'];
        if (rtrim($uri->path(), '/') !== '/admin/take' || $uri->method() !== 'POST') {
            return;
        }

        $post = $uri->post() ?: [];
        $task = $post['task'] ?? '';
        $slug = (string)($post['slug'] ?? '');

        switch ($task) {
            case 'save_event':
                $title = (string)($post['title'] ?? '');
                $date = (string)($post['date'] ?? '');
                if ($slug === '' || $slug === 'new') {
                    $slug = TakeStorage::createEvent($title, $date);
                } else {
                    TakeStorage::updateEvent($slug, $title, $date);
                }
                $this->grav->redirectLangSafe('/admin/take?task=edit&slug=' . rawurlencode($slug));
                break;

            case 'delete_event':
                TakeStorage::deleteEvent($slug);
                $this->grav->redirectLangSafe('/admin/take');
                break;

            case 'save_entry':
                $index = (int)($post['index'] ?? -1);
                $name = (string)($post['name'] ?? '');
                $items = $this->splitItems((string)($post['items'] ?? ''));
                if ($index < 0) {
                    TakeStorage::addEntry($slug, $name, (string)($post['email'] ?? ''), $items);
                } else {
                    TakeStorage::updateEntry($slug, $index, $name, $items);
                }
                $this->grav->redirectLangSafe('/admin/take?task=edit&slug=' . rawurlencode($slug));
                break;

            case 'delete_entry':
                TakeStorage::deleteEntry($slug, (int)($post['index'] ?? -1));
                $this->grav->redirectLangSafe('/admin/take?task=edit&slug=' . rawurlencode($slug));
                break;
        }
    }

    /**
     * Anmeldeformular im Frontend verarbeiten (Shortcode-Ausgabe erfolgt separat).
     */
    public function onPagesInitialized(): void
    {
        $uri = $this->grav['uri'];
        if ($uri->method() !== 'POST') {
            return;
        }

        $post = $uri->post() ?: [];
        if (($post['take_action'] ?? '') !== 'signup') {
            return;
        }

        $nonce = (string)($post['take_nonce'] ?? '');
        $session = $this->grav['session'];

        if (!Security::verifyNonce($nonce, 'take-form')) {
            $session->take_flash = ['type' => 'error', 'message' => 'PLUGIN_TAKE.ERROR_NONCE'];
            $this->grav->redirectLangSafe($uri->path());
            return;
        }

        $slug = (string)($post['slug'] ?? '');
        $name = trim((string)($post['name'] ?? ''));
        $email = trim((string)($post['email'] ?? ''));
        $items = array_values((array)($post['items'] ?? []));

        if ($slug === '' || !TakeStorage::getEvent($slug) || $name === '') {
            $session->take_flash = ['type' => 'error', 'message' => 'PLUGIN_TAKE.ERROR_INVALID'];
            $this->grav->redirectLangSafe($uri->path());
            return;
        }

        TakeStorage::addEntry($slug, $name, $email, $items);
        $session->take_flash = ['type' => 'success', 'message' => 'PLUGIN_TAKE.SUCCESS'];
        $this->grav->redirectLangSafe($uri->path());
    }

    public function onShortcodeHandlers(): void
    {
        $this->grav['shortcode']->registerAllShortcodes(__DIR__ . '/classes/shortcodes');
    }

    /**
     * Rendert eine oder alle Feiern (Formular + Liste) für den Shortcode.
     */
    public function renderShortcode(?string $slug = null): string
    {
        $events = $slug ? array_filter([$slug => TakeStorage::getEvent($slug)]) : TakeStorage::listEvents();

        $flash = null;
        $session = $this->grav['session'];
        if (isset($session->take_flash)) {
            $flash = $session->take_flash;
            unset($session->take_flash);
        }

        return $this->grav['twig']->twig()->render('partials/take.html.twig', [
            'events' => $events,
            'flash' => $flash,
            'nonce' => Security::getNonce('take-form'),
            'current_uri' => $this->grav['uri']->path(),
        ]);
    }

    protected function splitItems(string $raw): array
    {
        $lines = preg_split('/\r\n|\r|\n/', $raw) ?: [];
        return array_values(array_filter(array_map('trim', $lines), static function ($v) {
            return $v !== '';
        }));
    }
}
