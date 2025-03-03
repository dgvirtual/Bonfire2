<?php

namespace Tests\Groups;

use Bonfire\Groups\Module;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/**
 * @internal
 */
final class GroupsModuleTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $refresh = true;
    protected $namespace;

    protected function setUp(): void
    {
        parent::setUp();

        // Imitate being on the dashboard page
        $_SERVER['REQUEST_URI'] = config('App')->baseURL . '/' . ADMIN_AREA;

        // Mock the settings to enable widgets
        $this->mockSettings();
    }

    protected function mockSettings()
    {
        // Mock the setting function to return 'on' for specific widget settings
        helper('setting');
        setting()->set('Stats.Stats_userGroups643', 'on');
        setting()->set('Stats.Stats_usersByGroupLine123', 'on');
        setting()->set('Stats.Stats_usersByGroupBar345', 'on');
        setting()->set('Stats.Stats_usersByGroupDou654', 'on');
        setting()->set('Stats.Stats_usersByGroupPie223', 'on');
        setting()->set('Stats.Stats_usersByGroupPiePolar645', 'on');
    }

    public function testInitAdmin()
    {
        $module = new Module();
        $module->initAdmin();

        // Assert that the widgets have been added to the collection
        $widgets          = service('widgets');
        $statsCollection  = $widgets->widget('stats')->collection('stats');
        $chartsCollection = $widgets->widget('charts')->collection('charts');

        $this->assertNotEmpty($statsCollection->items());
        $this->assertNotEmpty($chartsCollection->items());
    }
}
