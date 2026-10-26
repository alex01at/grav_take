<?php

declare(strict_types=1);

namespace Grav\Plugin;

use Composer\Autoload\ClassLoader;
use Grav\Common\Plugin;
use Grav\Events\PermissionsRegisterEvent;
use Grav\Framework\Acl\PermissionsReader;
use Grav\Plugin\Api\PermissionResolver;
use Grav\Plugin\Take\ApiController;
use Grav\Plugin\Take\EntryStore;
use RocketTheme\Toolbox\Event\Event;

/**
 * Eintragungslisten für Feiern: Feiern (Name+Datum) werden in der
 * Plugin-Konfiguration gepflegt, das Formular erscheint überall, wo [take]
 * im Seiteninhalt steht, Eintragungen landen als JSON in user/data/take/
 * und werden in admin2 unter /plugin/take angezeigt.
 */
class TakePlugin extends Plugin
{
    private const MARKER = '[take]';

    /** Fehler/Eingaben des aktuellen POST, damit das Formular sie wieder anzeigt. */
    private ?string $error = null;
    private array $posted = [];

    public static function getSubscribedEvents(): array
    {
        return [
            'onPluginsInitialized' => ['onPluginsInitialized', 0],
            PermissionsRegisterEvent::class => ['onRegisterPermissions', 0],
            // Werden nur vom `api`-Plugin (admin2) gefeuert.
            'onApiRegisterRoutes' => ['onApiRegisterRoutes', 0],
            'onApiSidebarItems' => ['onApiSidebarItems', 0],
            'onApiPluginPageInfo' => ['onApiPluginPageInfo', 0],
        ];
    }

    public function autoload(): ClassLoader
    {
        $loader = new ClassLoader();
        $loader->setPsr4('Grav\\Plugin\\Take\\', __DIR__ . '/classes');
        $loader->register();

        return $loader;
    }

    public function onRegisterPermissions(PermissionsRegisterEvent $event): void
    {
        $actions = PermissionsReader::fromYaml('plugin://' . $this->name . '/permissions.yaml');
        $event->permissions->addActions($actions);
    }

    public function onPluginsInitialized(): void
    {
        if ($this->isAdmin()) {
            return;
        }

        $this->enable([
            'onPageInitialized' => ['onPageInitialized', 0],
            'onTwigTemplatePaths' => ['onTwigTemplatePaths', 0],
            'onOutputGenerated' => ['onOutputGenerated', 0],
        ]);
    }

    /*
     * ---------------------------------------------------------------
     * Frontend
     * ---------------------------------------------------------------
     */

    public function onPageInitialized(): void
    {
        $request = $_POST;
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || ($request['take_action'] ?? '') !== 'eintragen') {
            return;
        }

        // Honeypot gegen Spam-Bots: echte Besucher sehen das Feld nicht.
        if (!empty($request['take_website'])) {
            $this->redirectToSuccess();
        }

        $eventKey = (string) ($request['take_event_key'] ?? '');
        $this->posted = [
            'event_key' => $eventKey,
            'name' => (string) ($request['take_name'] ?? ''),
            'email' => (string) ($request['take_email'] ?? ''),
            'items' => (array) ($request['take_items'] ?? []),
        ];

        $result = EntryStore::create($eventKey, $this->posted['name'], $this->posted['email'], $this->posted['items']);
        if (isset($result['error'])) {
            $this->error = $result['error'];
            return;
        }

        $this->redirectToSuccess();
    }

    /** Post/Redirect/Get, damit ein Neuladen nicht doppelt einträgt. */
    private function redirectToSuccess(): void
    {
        $uri = $this->grav['uri'];
        $this->grav->redirect($uri->path() . '?eintragung=ok#take-eintragung', 303);
    }

    public function onTwigTemplatePaths(): void
    {
        $this->grav['twig']->twig_paths[] = __DIR__ . '/templates';
    }

    /**
     * Ersetzt den Platzhalter erst in der fertigen Ausgabe – so bleibt die
     * Liste trotz Seiten-Cache immer aktuell.
     */
    public function onOutputGenerated(): void
    {
        $output = $this->grav->output;
        if (!is_string($output) || !str_contains($output, self::MARKER)) {
            return;
        }

        $html = $this->render();
        // Markdown packt den Platzhalter meist in einen eigenen Absatz.
        $output = str_replace('<p>' . self::MARKER . '</p>', $html, $output);
        $this->grav->output = str_replace(self::MARKER, $html, $output);
    }

    public function render(): string
    {
        return $this->grav['twig']->processTemplate('take/eintragung.html.twig', [
            'events' => EntryStore::allGrouped(),
            'success' => ($_GET['eintragung'] ?? '') === 'ok' && $this->error === null,
            'success_text' => 'Danke für deine Eintragung!',
            'error' => $this->error,
            'posted' => $this->posted,
        ]);
    }

    /*
     * ---------------------------------------------------------------
     * admin2: Menüpunkt + Seite /plugin/take (Web Component unter
     * admin-next/pages/take.js)
     * ---------------------------------------------------------------
     */

    public function onApiRegisterRoutes(Event $event): void
    {
        $routes = $event['routes'];
        $routes->get('/take/events', [ApiController::class, 'events']);
        $routes->patch('/take/events/{eventKey}/{id}', [ApiController::class, 'update']);
        $routes->delete('/take/events/{eventKey}/{id}', [ApiController::class, 'delete']);
        $routes->get('/take/_badge', [ApiController::class, 'badge']);
    }

    public function onApiSidebarItems(Event $event): void
    {
        if (!$this->userCanManage($event['user'])) {
            return;
        }

        $items = $event['items'] ?? [];
        $items[] = [
            'id' => 'take',
            'plugin' => 'take',
            'label' => 'Feiern',
            'icon' => 'fa-glass-cheers',
            'route' => '/plugin/take',
            'priority' => 10,
            'badgeEndpoint' => '/take/_badge',
            'authorize' => ['api.take', 'api.super', 'admin.super'],
        ];
        $event['items'] = $items;
    }

    public function onApiPluginPageInfo(Event $event): void
    {
        if ((string) $event['plugin'] !== 'take' || !$this->userCanManage($event['user'])) {
            return;
        }

        $event['definition'] = [
            'id' => 'take',
            'plugin' => 'take',
            'title' => 'Feiern',
            'icon' => 'glass-cheers',
            'page_type' => 'component',
            'actions' => [
                [
                    'id' => 'events',
                    'label' => 'Feiern anlegen',
                    'icon' => 'fa-pen',
                    'navigate' => '/plugins/take',
                ],
            ],
        ];
    }

    /**
     * Konten aus admin2 tragen nur `api.*`-Rechte, Konten aus dem klassischen
     * Admin nur `admin.*` – daher beide prüfen.
     */
    private function userCanManage($user): bool
    {
        if (!$user) {
            return false;
        }
        if ($user->authorize('admin.super')) {
            return true;
        }

        $resolver = new PermissionResolver($this->grav['permissions'] ?? null);

        return (bool) ($resolver->resolve($user, 'api.super') || $resolver->resolve($user, 'api.take')
            || $resolver->resolve($user, 'admin.super'));
    }
}
