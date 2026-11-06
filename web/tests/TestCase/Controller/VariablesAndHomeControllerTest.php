<?php

declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use App\Test\Support\AdminIntegrationTestCase;

/**
 * Variables, dashboard, search and administration pages.
 */
class VariablesAndHomeControllerTest extends AdminIntegrationTestCase
{
    /**
     * A variable belongs to the record it was added to.
     *
     * @return void
     */
    public function testVariableLifecycle(): void
    {
        foreach (['/variables/view/host/1', '/variables/view/package/1', '/variables/view/profile/1', '/variables/add/host/1', '/variables/edit/6'] as $url) {
            $this->get($url);
            $this->assertResponseOk($url);
        }

        $this->post('/variables/add/host/4', ['name' => 'ROOM', 'value' => 'dev']);
        $this->assertRedirect(['action' => 'view', 'host', 4]);
        $variable = $this->fetchTable('Variables')->find()->where(['ref_id' => 4, 'ref_type' => 2])->firstOrFail();

        $this->post('/variables/edit/' . $variable->id, ['name' => 'ROOM', 'value' => 'changed']);
        $this->assertSame('changed', $this->fetchTable('Variables')->get($variable->id)->value);

        $this->post('/variables/delete/' . $variable->id);
        $this->assertRedirect(['action' => 'view', 'host', 4]);
        $this->assertFalse($this->fetchTable('Variables')->exists(['id' => $variable->id]));
    }

    /**
     * Owner types other than package, profile and host do not exist.
     *
     * @return void
     */
    public function testUnknownVariableOwner(): void
    {
        $this->get('/variables/view/users/1');
        $this->assertResponseCode(404);

        $this->get('/variables/view/host/999');
        $this->assertResponseCode(404);
    }

    /**
     * The dashboard counts records.
     *
     * @return void
     */
    public function testDashboard(): void
    {
        $this->get('/');

        $this->assertResponseOk();
        $this->assertResponseContains('# Enabled');
        $this->assertResponseContains('Plain safe commands');
    }

    /**
     * Search covers names, identifiers and notes, and can be narrowed.
     *
     * @return void
     */
    public function testSearch(): void
    {
        $this->get('/search?type=all&q=accentuées');
        $this->assertResponseContains('Found <b>1 result</b>');

        $this->get('/search?type=hosts&q=room');
        $this->assertResponseContains('Found <b>2 results</b>');
        $this->assertResponseNotContains('Packages (');

        $this->get('/search?q=');
        $this->assertResponseContains('Unable to complete your search request');
    }

    /**
     * The admin page shows settings without any password hash.
     *
     * @return void
     */
    public function testAdminPage(): void
    {
        $this->get('/admin');

        $this->assertResponseOk();
        $this->assertResponseContains('Protect XML output');
        $this->assertResponseNotContains('argon2');
    }
}
