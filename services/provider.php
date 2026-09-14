<?php

/**
 * @package     WmtHoneypot
 * @subpackage  System.wmthoneypot
 *
 * @copyright   (C) 2026 WebMarkeThink
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Wmt\Plugin\System\WmtHoneypot\Extension\WmtHoneypot;

return new class () implements ServiceProviderInterface {
    public function register(Container $container)
    {
        $container->set(
            PluginInterface::class,
            $container->lazy(WmtHoneypot::class, function (Container $container) {
                $plugin = new WmtHoneypot(
                    (array) PluginHelper::getPlugin('system', 'wmthoneypot')
                );
                $plugin->setApplication(Factory::getApplication());

                return $plugin;
            })
        );
    }
};
