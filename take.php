<?php
namespace Grav\Plugin;

use Grav\Common\Plugin;
use Grav\Common\Security;

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
        $this->enable([
            'onTwigTemplatePaths' => ['onTwigTemplatePaths', 0],
            'onTwigInitialized' => ['onTwigInitialized', 0],
        ]);

        if ($this->isAdmin()) {
            return;
        }

        $this->enable([
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

    public function onTwigInitialized(): void
    {
        $this->grav['twig']->twig()->addFunction(
            new \Twig\TwigFunction('take_render', [$this, 'renderShortcode'], ['is_safe' => ['html']])
        );
    }

    /**
     * Anmeldeformular im Frontend verarbeiten: hängt einen Eintrag an die
     * Frontmatter der angegebenen Feier-Seite an und speichert sie.
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

        $session = $this->grav['session'];
        $nonce = (string)($post['take_nonce'] ?? '');

        if (!Security::verifyNonce($nonce, 'take-form')) {
            $session->take_flash = ['type' => 'error', 'message' => 'PLUGIN_TAKE.ERROR_NONCE'];
            $this->grav->redirectLangSafe($uri->path());
            return;
        }

        $route = (string)($post['route'] ?? '');
        $name = trim((string)($post['name'] ?? ''));
        $email = trim((string)($post['email'] ?? ''));
        $items = array_values((array)($post['items'] ?? []));
        $items = array_values(array_filter(array_map('trim', $items), static function ($v) {
            return $v !== '';
        }));

        $page = $route !== '' ? $this->grav['pages']->find($route) : null;

        if (!$page || $page->template() !== 'feier' || $name === '') {
            $session->take_flash = ['type' => 'error', 'message' => 'PLUGIN_TAKE.ERROR_INVALID'];
            $this->grav->redirectLangSafe($uri->path());
            return;
        }

        $header = $page->header();
        $entries = (array)($header->entries ?? []);
        $entries[] = [
            'name' => $name,
            'email' => $email,
            'items' => implode(', ', $items),
        ];
        $header->entries = $entries;
        $page->header($header);
        $page->save();

        $session->take_flash = ['type' => 'success', 'message' => 'PLUGIN_TAKE.SUCCESS'];
        $this->grav->redirectLangSafe($uri->path());
    }

    public function onShortcodeHandlers(): void
    {
        $this->grav['shortcode']->registerAllShortcodes(__DIR__ . '/classes/shortcodes');
    }

    /**
     * Rendert eine (per Route) oder alle Feiern (Formular + Liste) für den Shortcode.
     */
    public function renderShortcode(?string $route = null): string
    {
        $events = [];
        if ($route) {
            $page = $this->grav['pages']->find($route);
            if ($page && $page->template() === 'feier') {
                $events[$page->route()] = $page;
            }
        } else {
            foreach ($this->grav['pages']->all() as $page) {
                if ($page->template() === 'feier') {
                    $events[$page->route()] = $page;
                }
            }
        }

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
}
