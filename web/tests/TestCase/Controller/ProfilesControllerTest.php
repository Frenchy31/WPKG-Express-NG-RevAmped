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
        foreach (['/profiles', '/profiles/view/1', '/profiles/add', '/profiles/edit/1', '/profiles/packages/1', '/profiles/hosts/1'] as $url) {
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

    /**
     * Collects the rendered rows of the hosts table as name => cells text.
     *
     * @param string $url Page to fetch.
     * @return array<int, array<int, string>> Text of the cells of each row, in display order.
     */
    private function hostRows(string $url): array
    {
        $this->get($url);
        $this->assertResponseOk();
        $document = new \DOMDocument();
        $document->loadHTML((string)$this->_response->getBody(), LIBXML_NOERROR);
        $rows = [];
        foreach ((new \DOMXPath($document))->query('//table//tr[td]') as $row) {
            $rows[] = array_map(
                fn(\DOMNode $cell): string => trim($cell->textContent),
                iterator_to_array($row->getElementsByTagName('td')),
            );
        }

        return $rows;
    }

    /**
     * Main and additional hosts are listed in matching order.
     *
     * @return void
     */
    public function testHostsListsMainAndAdditionalInPositionOrder(): void
    {
        $rows = $this->hostRows('/profiles/hosts/2');

        $this->assertSame(
            [
                ['Yes', '1', 'room2-pc.+', 'Main'],
                ['Yes', '2', 'room1-pc.+', 'Additional'],
                ['Yes', '4', '^dev-[0-9]+$', 'Additional'],
            ],
            array_map(fn(array $cells): array => array_slice($cells, 0, 4), $rows),
        );
        $this->assertResponseContains("Hosts of Profile '<a href=\"/profiles/view/2\">office</a>'");
        $this->assertResponseContains('<a href="/hosts/view/2">room2-pc.+</a>');
    }

    /**
     * A disabled host is marked and shown as No.
     *
     * @return void
     */
    public function testHostsMarksDisabledHost(): void
    {
        $rows = $this->hostRows('/profiles/hosts/1');

        $this->assertSame(['No', '3', 'old-pc.+', 'Main'], array_slice($rows[1], 0, 4));
        $this->assertSame(['room1-pc.+', 'old-pc.+', '.+'], array_column($rows, 2));
        $this->assertResponseContains('<tr class="disabled">');
        $this->assertSame(1, substr_count((string)$this->_response->getBody(), 'class="disabled"'));
    }

    /**
     * A host both main and additional appears once, as Main.
     *
     * @return void
     */
    public function testHostsShowsMainAndAdditionalHostOnce(): void
    {
        $this->fetchTable('Profiles')->Hosts->link(
            $this->fetchTable('Profiles')->get(2),
            [$this->fetchTable('Hosts')->get(2)],
        );

        $rows = $this->hostRows('/profiles/hosts/2');

        $this->assertSame(['room2-pc.+', 'room1-pc.+', '^dev-[0-9]+$'], array_column($rows, 2));
        $this->assertSame('Main', $rows[0][3]);
    }

    /**
     * A profile without host shows a message.
     *
     * @return void
     */
    public function testHostsEmpty(): void
    {
        $profiles = $this->fetchTable('Profiles');
        $profile = $profiles->saveOrFail($profiles->newEntity(['id_text' => 'lonely']));

        $this->get('/profiles/hosts/' . $profile->id);

        $this->assertResponseOk();
        $this->assertResponseContains('<div class="message">No Hosts found</div>');
        $this->assertResponseNotContains('<table');
    }

    /**
     * An unknown profile answers 404.
     *
     * @return void
     */
    public function testHostsUnknownProfile(): void
    {
        $this->get('/profiles/hosts/999');

        $this->assertResponseCode(404);
    }

    /**
     * The page is read-only.
     *
     * @return void
     */
    public function testHostsRejectsPost(): void
    {
        $this->post('/profiles/hosts/1');

        $this->assertResponseCode(405);
    }

    /**
     * Anonymous visitors are sent to the login page.
     *
     * @return void
     */
    public function testHostsRedirectsAnonymousVisitorToLogin(): void
    {
        $this->session(['Auth' => null]);

        $this->get('/profiles/hosts/1');

        $this->assertRedirectContains('/login');
    }

    /**
     * The profile page links to its hosts.
     *
     * @return void
     */
    public function testViewLinksToHosts(): void
    {
        $this->get('/profiles/view/2');

        $this->assertResponseContains('href="/profiles/hosts/2"');
    }

    /**
     * Deleting a profile drops its additional-profile rows and keeps the hosts.
     *
     * @return void
     */
    public function testDeleteProfileRemovesHostsProfilesRowsOnly(): void
    {
        $this->post('/profiles/delete/4');

        $joins = $this->fetchTable('Profiles')->Hosts->junction();
        $this->assertSame(0, $joins->find()->where(['profile_id' => 4])->count());
        $this->assertSame(3, $joins->find()->count());
        $this->assertSame(5, $this->fetchTable('Hosts')->find()->count());
    }

    /**
     * Only hosts that are not attached yet can be added.
     *
     * @return void
     */
    public function testHostsFormOffersUnattachedHostsOnly(): void
    {
        $this->get('/profiles/hosts/2');

        $this->assertResponseContains('<option value="5">.+</option>');
        $this->assertResponseContains('<option value="3">old-pc.+</option>');
        $this->assertResponseNotContains('<option value="1">');
        $this->assertResponseNotContains('<option value="2">');
        $this->assertResponseNotContains('<option value="4">');
    }

    /**
     * Additional hosts can be removed with a warning, main hosts only link to the host form.
     *
     * @return void
     */
    public function testHostsActionsDependOnTheLink(): void
    {
        $this->get('/profiles/hosts/2');

        $this->assertResponseContains('/profiles/remove-host/2/1');
        $this->assertResponseContains('clients uninstall the packages');
        $this->assertResponseNotContains('/profiles/remove-host/2/2');
        $this->assertResponseContains('/hosts/edit/2');
    }

    /**
     * Adding a host links it as an additional profile, which reaches the feed.
     *
     * @return void
     */
    public function testAddHost(): void
    {
        $this->post('/profiles/add-host/2', ['host_id' => 5]);

        $this->assertRedirect(['action' => 'hosts', 2]);
        $this->assertFlashMessage("Host '.+' added to Profile 'office'.");
        $this->assertSame(1, $this->hostLinks(5, 2));

        $this->get('/hosts/view/5.xml');
        $this->assertResponseContains('<profile id="office"/>');
    }

    /**
     * The form can fill a profile without hosts.
     *
     * @return void
     */
    public function testEmptyProfileOffersTheForm(): void
    {
        $profile = $this->fetchTable('Profiles')->saveOrFail(
            $this->fetchTable('Profiles')->newEntity(['id_text' => 'empty']),
        );

        $this->get('/profiles/hosts/' . $profile->id);

        $this->assertResponseContains('No Hosts found');
        $this->assertResponseContains('/profiles/add-host/' . $profile->id);
    }

    /**
     * A main host is not linked again, and nothing is duplicated.
     *
     * @return void
     */
    public function testAddHostRejectsAttachedHosts(): void
    {
        $this->post('/profiles/add-host/2', ['host_id' => 2]);
        $this->assertSame(0, $this->hostLinks(2, 2));
        $this->assertFlashMessage("The Profile 'office' is already the main profile of Host 'room2-pc.+'.");

        $this->post('/profiles/add-host/2', ['host_id' => 1]);
        $this->assertSame(1, $this->hostLinks(1, 2));
        $this->assertFlashMessage("Host 'room1-pc.+' is already attached to Profile 'office'.");
    }

    /**
     * Clients ignore disabled profiles, so linking one would do nothing silently.
     *
     * @return void
     */
    public function testAddHostRejectsDisabledProfile(): void
    {
        $this->get('/profiles/hosts/4');
        $this->assertResponseNotContains('/profiles/add-host/4');

        $this->post('/profiles/add-host/4', ['host_id' => 5]);

        $this->assertSame(0, $this->hostLinks(5, 4));
        $this->assertFlashMessage("The Profile 'retired' is disabled: clients would ignore this link.");
    }

    /**
     * Unknown records and GET requests change nothing.
     *
     * @return void
     */
    public function testAddHostRejectsBadRequests(): void
    {
        $this->post('/profiles/add-host/2', ['host_id' => 999]);
        $this->assertResponseCode(404);

        $this->post('/profiles/add-host/999', ['host_id' => 5]);
        $this->assertResponseCode(404);

        $this->get('/profiles/add-host/2');
        $this->assertResponseCode(405);
    }

    /**
     * Removing a host only cuts this link.
     *
     * @return void
     */
    public function testRemoveHost(): void
    {
        $this->post('/profiles/remove-host/2/1');

        $this->assertRedirect(['action' => 'hosts', 2]);
        $this->assertFlashMessage("Host 'room1-pc.+' removed from Profile 'office'.");
        $this->assertSame(0, $this->hostLinks(1, 2));
        $this->assertSame(1, $this->hostLinks(1, 3));
        $this->assertTrue($this->fetchTable('Hosts')->exists(['id' => 1]));
        $this->assertSame(1, $this->fetchTable('Hosts')->get(1)->mainprofile_id);
    }

    /**
     * A host keeps its mandatory main profile.
     *
     * @return void
     */
    public function testRemoveHostRejectsMainProfile(): void
    {
        $this->post('/profiles/remove-host/2/2');

        $this->assertSame(2, $this->fetchTable('Hosts')->get(2)->mainprofile_id);
        $this->assertFlashMessage(
            "The Profile 'office' is the main profile of Host 'room2-pc.+': change it on the host.",
        );
    }

    /**
     * Removal needs a POST request.
     *
     * @return void
     */
    public function testRemoveHostRejectsGet(): void
    {
        $this->get('/profiles/remove-host/2/1');

        $this->assertResponseCode(405);
        $this->assertSame(1, $this->hostLinks(1, 2));
    }

    /**
     * Counts the additional links between a host and a profile.
     *
     * @param int $hostId Host id.
     * @param int $profileId Profile id.
     * @return int
     */
    private function hostLinks(int $hostId, int $profileId): int
    {
        return $this->fetchTable('Profiles')->Hosts->junction()->find()
            ->where(['host_id' => $hostId, 'profile_id' => $profileId])
            ->count();
    }
}
