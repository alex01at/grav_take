<?php
namespace Grav\Plugin\Shortcodes;

use Grav\Common\Grav;
use Grav\Plugin\ShortcodeCore\Shortcode;
use Thunder\Shortcode\Shortcode\ShortcodeInterface;

/**
 * [take]            -> alle Feiern mit Liste + Anmeldeformular
 * [take slug="xyz"] -> nur eine bestimmte Feier
 */
class TakeShortcode extends Shortcode
{
    public function init(): void
    {
        $this->shortcode->getHandlers()->add('take', function (ShortcodeInterface $sc) {
            $grav = Grav::instance();
            /** @var \Grav\Plugin\TakePlugin $plugin */
            $plugin = $grav['plugins']->get('take');
            $slug = $sc->getParameter('slug');

            return $plugin ? $plugin->renderShortcode($slug) : '';
        });
    }
}
