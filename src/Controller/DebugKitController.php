<?php
declare(strict_types=1);

namespace AttributeRegistry\Controller;

use AttributeRegistry\AttributeRegistry;
use AttributeRegistry\ValueObject\AttributeInfo;
use Cake\Controller\Controller;
use Cake\Http\Response;
use Cake\View\JsonView;

/**
 * Controller for DebugKit panel AJAX actions.
 *
 * Provides endpoints for re-discovering attributes from the panel.
 */
class DebugKitController extends Controller
{
    /**
     * @return array<string, class-string>
     */
    public function viewClasses(): array
    {
        return [
            'json' => JsonView::class,
        ];
    }

    /**
     * Re-discover all attributes.
     *
     * Clears the cache and performs a fresh discovery scan.
     *
     * @return \Cake\Http\Response|null JSON response with discovery results
     */
    public function discover(): ?Response
    {
        $this->request->allowMethod(['POST']);

        $registry = AttributeRegistry::getInstance();
        $registry->clearCache();

        $attributes = $registry->discover();

        $this->set([
            'success' => true,
            'count' => count($attributes),
            'attributes' => array_map(
                fn(AttributeInfo $attr): array => $attr->toArray(),
                $attributes,
            ),
        ]);
        $this->viewBuilder()->setOption('serialize', ['success', 'count', 'attributes']);

        return null;
    }
}
