<?php
declare(strict_types=1);

namespace TestLocalPlugin\Controller;

use Cake\Controller\Controller;
use TestLocalPlugin\Attribute\LocalPluginRoute;

/**
 * Test controller with attributes in local plugin
 */
#[LocalPluginRoute('/local-test', name: 'local_test')]
class TestLocalController extends Controller
{
    #[LocalPluginRoute('/action', method: 'GET')]
    public function testAction(): void
    {
    }
}
