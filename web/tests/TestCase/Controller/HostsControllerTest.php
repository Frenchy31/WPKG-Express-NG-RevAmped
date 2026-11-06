<?php

declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use App\Test\Support\AdminIntegrationTestCase;

/**
 * Host management.
 */
class HostsControllerTest extends AdminIntegrationTestCase
{
    /**
     * Pages render with the reference data.
     *
     * @return void
     */
    public function testPagesRender(): void
    {
        foreach (['/hosts', '/hosts/view/1', '/hosts/add', '/hosts/edit/1', '/hosts/profiles/1'] as $url) {
            $this->get($url);
            $this->assertResponseOk($url);
        }
        $this->get('/hosts/view/1');
        $this->assertResponseContains('room1-pc.+');
    }

    /**
     * A host is added at the end of the matching order.
     *
     * @return void
     */
    public function testAdd(): void
    {
        $this->post('/hosts/add', ['name' => 'lab-.*', 'notes' => '', 'mainprofile_id' => 2]);

        $host = $this->fetchTable('Hosts')->find()->where(['name' => 'lab-.*'])->firstOrFail();
        $this->assertRedirect(['action' => 'view', $host->id]);
        $this->assertSame(6, $host->position);
        $this->assertTrue($host->enabled);
    }

    /**
     * Names must compile as regular expressions and stay unique.
     *
     * @return void
     */
    public function testAddRejectsInvalidNames(): void
    {
        $this->post('/hosts/add', ['name' => 'bad(regex', 'mainprofile_id' => 2]);
        $this->assertResponseOk();
        $this->assertResponseContains('invalid regular expression');

        $this->post('/hosts/add', ['name' => 'room1-pc.+', 'mainprofile_id' => 2]);
        $this->assertResponseContains('That host already exists.');

        $this->post('/hosts/add', ['name' => 'valid', 'mainprofile_id' => 999]);
        $this->assertResponseContains('main profile could not be found');
    }

    /**
     * The list goes back to the host after a move.
     *
     * @return void
     */
    public function testMoveKeepsAContinuousOrder(): void
    {
        $names = fn(): array => $this->fetchTable('Hosts')->find()
            ->orderBy(['position' => 'ASC'])->all()->extract('name')->toList();

        $this->post('/hosts/move/5/top');
        $this->assertRedirect(['action' => 'index']);
        $this->assertSame(['.+', 'room2-pc.+', 'room1-pc.+', 'old-pc.+', '^dev-[0-9]+$'], $names());

        $this->post('/hosts/move/5/down');
        $this->assertSame(['room2-pc.+', '.+', 'room1-pc.+', 'old-pc.+', '^dev-[0-9]+$'], $names());

        $this->post('/hosts/move/2/bottom');
        $this->assertSame(['.+', 'room1-pc.+', 'old-pc.+', '^dev-[0-9]+$', 'room2-pc.+'], $names());

        $this->post('/hosts/move/5/up');
        $this->assertSame(['.+', 'room1-pc.+', 'old-pc.+', '^dev-[0-9]+$', 'room2-pc.+'], $names());
    }

    /**
     * Unknown directions and GET requests cannot move records.
     *
     * @return void
     */
    public function testMoveRejectsBadRequests(): void
    {
        $this->post('/hosts/move/5/sideways');
        $this->assertResponseCode(400);

        $this->get('/hosts/move/5/top');
        $this->assertResponseCode(405);
    }

    /**
     * Enable and disable only change the flag.
     *
     * @return void
     */
    public function testEnableDisable(): void
    {
        $this->post('/hosts/disable/1');
        $this->assertFalse($this->fetchTable('Hosts')->get(1)->enabled);

        $this->post('/hosts/enable/1');
        $this->assertTrue($this->fetchTable('Hosts')->get(1)->enabled);
    }

    /**
     * Additional profiles can be replaced and removed.
     *
     * @return void
     */
    public function testAdditionalProfiles(): void
    {
        $linked = fn(): array => $this->fetchTable('Hosts')->get(1, contain: ['Profiles'])->profiles;

        $this->post('/hosts/profiles/1', ['profiles' => ['_ids' => [3]]]);
        $this->assertRedirect(['action' => 'view', 1]);
        $this->assertSame(['dev'], array_map(fn($profile) => $profile->id_text, $linked()));

        $this->post('/hosts/remove-profile/1/3');
        $this->assertSame([], $linked());
    }

    /**
     * Deleting a host removes its variables and links, and nothing else.
     *
     * @return void
     */
    public function testDelete(): void
    {
        $this->post('/hosts/delete/1');

        $this->assertFalse($this->fetchTable('Hosts')->exists(['id' => 1]));
        $this->assertFalse($this->fetchTable('Variables')->exists(['ref_id' => 1, 'ref_type' => 2]));
        $this->assertTrue($this->fetchTable('Variables')->exists(['ref_id' => 1, 'ref_type' => 0]));
        $this->assertTrue($this->fetchTable('Profiles')->exists(['id' => 3]));
    }
}
