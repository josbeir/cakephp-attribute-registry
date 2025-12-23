<?php
declare(strict_types=1);

namespace TestApp;

use AttributeRegistry\AttributeRegistryPlugin;
use Cake\Http\BaseApplication;
use Cake\Http\MiddlewareQueue;
use Cake\Routing\Middleware\RoutingMiddleware;
use Cake\Routing\RouteBuilder;
use DebugKit\DebugKitPlugin;
use TestLocalPlugin\TestLocalPlugin;

/**
 * @extends BaseApplication<\TestApp\Application>
 */
class Application extends BaseApplication
{
    public function middleware(MiddlewareQueue $middlewareQueue): MiddlewareQueue
    {
        return $middlewareQueue->add(new RoutingMiddleware($this));
    }

    public function routes(RouteBuilder $routes): void
    {
        // Routes are loaded from the plugin
    }

    public function bootstrap(): void
    {
        // Add a fake DebugKit plugin to simulate it being loaded
        // This allows AttributeRegistry to detect DebugKit and register routes
        $this->addOptionalPlugin(DebugKitPlugin::class);
        $this->addPlugin(AttributeRegistryPlugin::class);

        // Add test local plugin (simulates a local plugin without packagePath)
        $this->addPlugin(TestLocalPlugin::class);
    }
}
