<?php
declare(strict_types=1);

namespace TestApp;

use AttributeRegistry\AttributeRegistryPlugin;
use Cake\Http\BaseApplication;
use Cake\Http\MiddlewareQueue;
use Cake\Routing\Middleware\RoutingMiddleware;
use Cake\Routing\RouteBuilder;

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
        $this->addPlugin(AttributeRegistryPlugin::class);
    }
}
