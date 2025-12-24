<?php
declare(strict_types=1);

namespace AttributeRegistry\Service;

use Cake\Core\Plugin;
use Cake\Core\PluginConfig;

/**
 * Resolves plugin paths for attribute scanning
 *
 * This class retrieves all enabled plugins (including CLI-only plugins)
 * regardless of the current request context (CLI vs web).
 */
class PluginPathResolver
{
    /**
     * Get paths for all enabled plugins
     *
     * This method returns paths for ALL plugins that are configured to load,
     * including those marked with 'onlyCli' => true. This ensures atomic
     * discovery where the same attributes are discovered regardless of whether
     * the discovery happens from CLI or web context.
     *
     * For local plugins without packagePath, falls back to Plugin::getCollection()
     * to retrieve the path from the loaded plugin instance.
     *
     * @return array<string> Array of absolute plugin paths
     */
    public function getEnabledPluginPaths(): array
    {
        $paths = [];
        $allPlugins = PluginConfig::getAppConfig();
        $pluginCollection = Plugin::getCollection();

        foreach ($allPlugins as $config) {
            if (($config['isLoaded'] ?? false) !== true) {
                continue;
            }

            // Use packagePath from config if available
            if (isset($config['packagePath'])) {
                $paths[] = $config['packagePath'];
            }
        }

        // Also check plugin collection for any plugins not in config
        // This ensures we don't miss plugins loaded directly via Plugin::getCollection()->add()
        foreach ($pluginCollection as $plugin) {
            $pluginPath = $plugin->getPath();
            if (!in_array($pluginPath, $paths, true)) {
                $paths[] = $pluginPath;
            }
        }

        return $paths;
    }
}
