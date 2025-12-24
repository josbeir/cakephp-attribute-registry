<?php
declare(strict_types=1);

namespace AttributeRegistry\Service;

use Cake\Core\Plugin;
use Cake\Core\PluginConfig;

/**
 * Locates and identifies plugins for attribute scanning
 *
 * This class retrieves all enabled plugins (including CLI-only plugins)
 * regardless of the current request context (CLI vs web).
 */
class PluginLocator
{
    /**
     * Cache for path to plugin name mapping
     *
     * @var array<string, string>|null
     */
    private ?array $pathToPluginMap = null;

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
        return array_keys($this->getPluginPathMap());
    }

    /**
     * Get mapping of plugin paths to plugin names
     *
     * Returns a mapping of absolute plugin paths to their corresponding plugin names.
     * Results are cached for performance.
     *
     * @return array<string, string> ['path/to/plugin' => 'PluginName']
     */
    public function getPluginPathMap(): array
    {
        if ($this->pathToPluginMap !== null) {
            return $this->pathToPluginMap;
        }

        $map = [];
        $allPlugins = PluginConfig::getAppConfig();
        $pluginCollection = Plugin::getCollection();

        foreach ($allPlugins as $config) {
            if (($config['isLoaded'] ?? false) !== true) {
                continue;
            }

            $pluginName = $config['name'] ?? null;

            // Use packagePath from config if available
            if (isset($config['packagePath']) && $pluginName) {
                $map[$config['packagePath']] = $pluginName;
            }
        }

        // Also check plugin collection for any plugins not in config
        // This ensures we don't miss plugins loaded directly via Plugin::getCollection()->add()
        foreach ($pluginCollection as $plugin) {
            $pluginPath = $plugin->getPath();
            $pluginName = $plugin->getName();

            if (!isset($map[$pluginPath])) {
                $map[$pluginPath] = $pluginName;
            }
        }

        $this->pathToPluginMap = $map;

        return $map;
    }

    /**
     * Get plugin name from file path
     *
     * Determines which plugin a file belongs to by checking if its path
     * starts with any known plugin path. Paths are checked in descending
     * length order to ensure more specific (longer) paths match first,
     * preventing issues with paths that are substrings of each other.
     *
     * @param string $filePath Absolute file path
     * @return string|null Plugin name or null if file is in App namespace
     */
    public function getPluginNameFromPath(string $filePath): ?string
    {
        $map = $this->getPluginPathMap();

        // Sort paths by length descending to check more specific paths first
        // This prevents '/plugins/Test' from matching before '/plugins/TestExtended'
        uksort($map, fn(string $a, string $b): int => strlen($b) - strlen($a));

        // Check if file path starts with any plugin path
        foreach ($map as $pluginPath => $pluginName) {
            if (str_starts_with($filePath, $pluginPath)) {
                return $pluginName;
            }
        }

        return null;
    }
}
