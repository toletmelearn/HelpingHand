<?php

namespace Tests\Feature\Admin;

use Tests\TestCase;
use App\Services\Registry\ErpRegistry;
use App\Contracts\Operations\CheckInterface;

class ErpRegistryTest extends TestCase
{
    /**
     * Test modules registration and retrieval.
     */
    public function test_erp_registry_can_register_and_get_modules()
    {
        $registry = new ErpRegistry();
        
        $registry->registerModule('TestModule', [
            'version' => '2.0.0',
            'description' => 'A test mock module.'
        ]);

        $this->assertTrue($registry->isModuleEnabled('TestModule'));
        $modules = $registry->getModules();
        
        $this->assertArrayHasKey('TestModule', $modules);
        $this->assertEquals('2.0.0', $modules['TestModule']['version']);
        $this->assertEquals('A test mock module.', $modules['TestModule']['description']);
    }

    /**
     * Test sidebar entries registration.
     */
    public function test_erp_registry_can_register_sidebar_entries()
    {
        $registry = new ErpRegistry();

        $registry->registerSidebarEntry('custom_section', [
            'title' => 'Custom Section',
            'icon' => 'bi-star',
            'links' => [
                ['title' => 'Link 1', 'url' => '/custom/link', 'icon' => 'bi-star-fill']
            ]
        ]);

        $sidebar = $registry->getSidebarEntries();
        $this->assertArrayHasKey('custom_section', $sidebar);
        $this->assertEquals('Custom Section', $sidebar['custom_section']['title']);
        $this->assertEquals('bi-star', $sidebar['custom_section']['icon']);
        $this->assertCount(1, $sidebar['custom_section']['links']);
        $this->assertEquals('/custom/link', $sidebar['custom_section']['links'][0]['url']);
    }

    /**
     * Test feature flags registration.
     */
    public function test_erp_registry_handles_feature_flags()
    {
        $registry = new ErpRegistry();

        $registry->registerFeatureFlag('flag_a', true);
        $registry->registerFeatureFlag('flag_b', false);

        $this->assertTrue($registry->getFeatureFlag('flag_a'));
        $this->assertFalse($registry->getFeatureFlag('flag_b'));

        $registry->setFeatureFlag('flag_b', true);
        $this->assertTrue($registry->getFeatureFlag('flag_b'));
    }

    /**
     * Test diagnostic check registration.
     */
    public function test_erp_registry_handles_diagnostic_checks()
    {
        $registry = new ErpRegistry();

        $check = new class implements CheckInterface {
            public function getName(): string { return 'Mock Check'; }
            public function getCategory(): string { return 'Testing'; }
            public function run(): array { return ['status' => 'success', 'message' => 'OK', 'meta' => []]; }
        };

        $registry->registerDiagnosticCheck($check);
        $checks = $registry->getDiagnosticChecks();

        $this->assertCount(1, $checks);
        $this->assertEquals('Mock Check', $checks[0]->getName());
    }
}
