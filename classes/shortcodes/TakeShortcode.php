<?php
namespace Grav\Plugin\Shortcodes;

use Grav\Common\Grav;
use Grav\Plugin\ShortcodeCore\Shortcode;
use Thunder\Shortcode\Shortcode\ShortcodeInterface;

/**
 * [take]                  -> alle Feier-Seiten mit Liste + Anmeldeformular
 * [take route="/feiern/sommerfest"] -> nur eine bestimmte Feier-Seite
 */
class TakeShortcode extends Shortcode
{
    public function init(): void
    {
        $this->shortcode->getHandlers()->add('take', function (ShortcodeInterface $sc) {
            $grav = Grav::instance();
            /** @var \Grav\Plugin\TakePlugin $plugin */
            $plugin = $grav['plugins']->get('take');
            $route = $sc->getParameter('route');

            return $plugin ? $plugin->renderShortcode($route) : '';
        });
    }
}
