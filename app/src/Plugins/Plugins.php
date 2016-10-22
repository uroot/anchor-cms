<?php

namespace Anchorcms\Plugins;

use Anchorcms\Mappers\MapperInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;

class Plugins
{
    /**
     * @var array of plugins that have been loaded
     */
    protected $loaded = [];

    /**
     * @var string path to plugin directory
     */
    protected $path;

    /**
     * @var object symfony event dispatcher
     */
    protected $events;

    public function __construct(string $path, EventDispatcher $events) {
        $this->path = $path;
        $this->events = $events;
    }

    /**
     * Scan plugin directory for plugin manifest files
     */
    public function getPlugins(): array
    {
        $plugins = [];

        if (! is_dir($this->path)) {
            mkdir($this->path, 0755, true);
        }

        $fi = new \FilesystemIterator($this->path, \FilesystemIterator::SKIP_DOTS);

        foreach ($fi as $file) {
            $manifest = $file->getPathname() . '/manifest.json';

            if (is_file($manifest)) {
                $jsonStr = file_get_contents($manifest);
                $attributes = json_decode($jsonStr, true);
                $plugins[] = new PluginManifest($file->getBasename(), $attributes);
            }
        }

        return $plugins;
    }

    /**
     * Get array of active plugins set in the meta table
     *
     * @param string path to plugins directory
     * @param object mapper of meta table
     * @return array
     */
    public function getActivePlugins(MapperInterface $meta): array
    {
        $jsonStr = $meta->key('plugins', '[]');
        $active = json_decode($jsonStr, true);

        // filter inactive plugins by folder name
        return array_filter($this->getPlugins(), function ($pluginManifest) use ($active) {
            return in_array($pluginManifest->getFolder(), $active);
        });
    }

    /**
     * Fetch a plugin manifest object by directory
     *
     * @param string full path to plugin folder name
     */
    public function getPluginByFolder(string $folder): PluginManifest
    {
        $manifest = $this->path . '/' . $folder . '/manifest.json';

        if (! is_file($manifest)) {
            throw new \RuntimeException(sprintf('manifest file not found for %s', $folder));
        }

        $jsonStr = file_get_contents($manifest);
        $attributes = json_decode($jsonStr, true);

        if (JSON_ERROR_NONE !== json_last_error()) {
            throw new \RuntimeException(sprintf('failed to decode manifest file: %s', json_last_error_msg()));
        }

        return new PluginManifest($folder, $attributes);
    }

    /**
     * Init active plugins
     *
     * @param string path to plugins directory
     * @param array of active plugins
     * @param object symfony event dispatcher
     */
    public function init(array $plugins)
    {
        foreach ($plugins as $pluginManifest) {
            // @todo: add namespace to loader
            // $composer->addPsr4($pluginManifest->getNamespace(), $pluginManifest->getFolder(), true);
            //
            $this->loaded[$pluginManifest->getFolder()] = $pluginManifest->getInstance();

            // todo: set the database connection on the plugin
            // if($pluginInstance instanceof PluginDatabaseInterface) {
            //     $pluginInstance->getDatabaseConnection($database, $prefix);
            // }

            $this->loaded[$pluginManifest->getFolder()]->getSubscribedEvents($this->events);
        }
    }

    /**
     * Get a active plugin by folder name
     *
     * @param string folder name
     * @return object AbstractPlugin
     */
    public function getActivePlugin(string $folder): AbstractPlugin
    {
        return $this->loaded[$folder];
    }
}
