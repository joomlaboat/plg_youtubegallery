<?php
/**
 * @package     YouTubeGallery
 * @subpackage  YouTubeGallery Content Plugin
 * @author      Ivan Komlev <support@joomlaboat.com>
 * @copyright   (C) 2024 Ivan Komlev. <https://www.joomlaboat.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 **/

\defined('_JEXEC') or die;

use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Version;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Joomla\Event\DispatcherInterface;
use YoutubeGallery\Plugin\Content\YoutubeGallery\Extension\YoutubeGallery;

require_once(JPATH_SITE . DIRECTORY_SEPARATOR .'plugins' . DIRECTORY_SEPARATOR .'content'
    . DIRECTORY_SEPARATOR .'youtubegallery'. DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'Extension' . DIRECTORY_SEPARATOR . 'youtubegallery.php');


return new class () implements ServiceProviderInterface {
    /**
     * Registers the service provider with a DI container.
     *
     * @param   Container  $container  The DI container.
     *
     * @return  void
     *
     * @since   4.4.0
     */
    public function register(Container $container): void
    {
        $versionObject = new Version;
        $version = (int)$versionObject->getShortVersion();

        if ($version >= 5) {
            $container->set(
                PluginInterface::class,
                function (Container $container) {

                    $containerInterface = $container->get(DispatcherInterface::class);

                    $plugin = new YoutubeGallery(
                        $containerInterface,
                        (array)PluginHelper::getPlugin('content', 'youtubegallery')
                    );
                    $plugin->setApplication(Factory::getApplication());

                    return $plugin;
                }
            );
        } else {
            $container->set(
                PluginInterface::class,
                function (Container $container) {
                    $dispatcher = $container->get(DispatcherInterface::class);
                    $plugin = new YoutubeGallery(
                        $dispatcher,
                        (array)PluginHelper::getPlugin('content', 'youtubegallery')
                    );
                    $plugin->setApplication(Factory::getApplication());

                    return $plugin;
                }
            );
        }
    }
};

