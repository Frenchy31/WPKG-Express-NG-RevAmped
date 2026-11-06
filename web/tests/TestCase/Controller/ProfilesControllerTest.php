<?php

declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use App\Test\Support\AdminIntegrationTestCase;

/**
 * Profile management.
 */
class ProfilesControllerTest extends AdminIntegrationTestCase
{
    /**
     * Pages render with the reference data.
     *
     * @return void
     */
    public function testPagesRender(): void
    {
        foreach (['/profiles', '/profiles/view/1', '/profiles/add', '/profiles/edit/1', '/profiles/packages/1'] as $url) {
            $this->get($url);
            $this->assertResponseOk($url);
        }
    }

    /**
     * Identifiers follow the legacy format.
     *
     * @return void
     */
    public function testAddValidatesIdentifier(): void
    {
        $this->post('/profiles/add', ['id_text' => 'new-profile_1']);
        $this->assertTrue($this->fetchTable('Profiles')->exists(['id_text' => 'new-profile_1']));

        $this->post('/profiles/add', ['id_text' => '-bad']);
        $this->assertResponseContains('must start with a letter or number');

        $this->post('/profiles/add', ['id_text' => 'base']);
        $this->assertResponseContains('That profile already exists.');
    }

    /**
     * A main profile can be neither disabled nor deleted.
     *
     * @return void
     */
    public function testMainProfileIsProtected(): void
    {
        $this->post('/profiles/disable/1');
        $this->assertFlashMessageAt(0, "The Profile 'base' cannot be deleted or disabled because it is the main profile for the following host(s): .+, old-pc.+, room1-pc.+");
        $this->assertTrue($this->fetchTable('Profiles')->get(1)->enabled);

        $this->post('/profiles/delete/1');
        $this->assertTrue($this->fetchTable('Profiles')->exists(['id' => 1]));

        $this->post('/profiles/edit/1', ['id_text' => 'base', 'enabled' => '0']);
        $this->assertTrue($this->fetchTable('Profiles')->get(1)->enabled);
    }

    /**
     * A profile no host relies on can be disabled and deleted.
     *
     * @return void
     */
    public function testUnusedProfile(): void
    {
        $this->post('/profiles/enable/4');
        $this->assertTrue($this->fetchTable('Profiles')->get(4)->enabled);

        $this->post('/profiles/disable/4');
        $this->assertFalse($this->fetchTable('Profiles')->get(4)->enabled);

        $this->post('/profiles/delete/4');
        $this->assertFalse($this->fetchTable('Profiles')->exists(['id' => 4]));
    }

    /**
     * Packages and dependencies can be replaced.
     *
     * @return void
     */
    public function testAssociations(): void
    {
        $this->post('/profiles/packages/1', ['packages' => ['_ids' => [9]]]);
        $this->assertSame(['accent'], array_map(
            fn($package) => $package->id_text,
            $this->fetchTable('Profiles')->get(1, contain: ['Packages'])->packages,
        ));

        $this->post('/profiles/remove-package/1/9');
        $this->assertSame([], $this->fetchTable('Profiles')->get(1, contain: ['Packages'])->packages);

        $this->post('/profiles/edit/2', ['id_text' => 'office', 'enabled' => '1', 'profile_dependencies' => ['_ids' => [3]]]);
        $this->assertSame(['dev'], array_map(
            fn($profile) => $profile->id_text,
            $this->fetchTable('Profiles')->get(2, contain: ['ProfileDependencies'])->profile_dependencies,
        ));
    }
}
