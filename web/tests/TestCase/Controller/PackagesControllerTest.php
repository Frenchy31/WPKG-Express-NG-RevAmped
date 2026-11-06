<?php

declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use App\Test\Support\AdminIntegrationTestCase;

/**
 * Package management, including checks, actions and exit codes.
 */
class PackagesControllerTest extends AdminIntegrationTestCase
{
    /**
     * Pages render with the reference data, whatever the check type.
     *
     * @return void
     */
    public function testPagesRender(): void
    {
        foreach (
            [
            '/packages', '/packages/view/2', '/packages/add', '/packages/edit/2',
            '/package-actions/view/4', '/package-actions/add/2', '/package-actions/edit/4',
            '/package-checks/add/2', '/package-checks/add/2?type=0&cond=3', '/package-checks/add/2?type=2&cond=8',
            '/package-checks/add/2?type=5', '/package-checks/edit/2', '/package-checks/edit/3',
            '/exit-codes/add/4', '/exit-codes/edit/1',
            ] as $url
        ) {
            $this->get($url);
            $this->assertResponseOk($url);
        }
    }

    /**
     * Output is escaped: stored values cannot inject markup in the interface.
     *
     * @return void
     */
    public function testPagesEscapeStoredValues(): void
    {
        $package = $this->fetchTable('Packages')->get(3);
        $package->name = '<script>alert(1)</script>';
        $this->fetchTable('Packages')->saveOrFail($package);

        $this->get('/packages/view/3');
        $this->assertResponseNotContains('<script>alert(1)</script>');
        $this->assertResponseContains('cmd /c echo a &amp; echo b');
    }

    /**
     * Blank optional fields get their defaults.
     *
     * @return void
     */
    public function testAddAppliesDefaults(): void
    {
        $this->post('/packages/add', [
            'name' => 'New', 'id_text' => 'new', 'revision' => ' ', 'priority' => '',
            'reboot' => 0, 'execute' => 0, 'notify' => 0,
        ]);

        $package = $this->fetchTable('Packages')->find()->where(['id_text' => 'new'])->firstOrFail();
        $this->assertSame('0', $package->revision);
        $this->assertSame(0, $package->priority);
    }

    /**
     * Fields follow the legacy validation.
     *
     * @return void
     */
    public function testAddValidatesFields(): void
    {
        $base = ['name' => 'New', 'id_text' => 'new', 'reboot' => 0, 'execute' => 0, 'notify' => 0];

        $this->post('/packages/add', ['revision' => 'abc'] + $base);
        $this->assertResponseContains('not formatted properly');

        $this->post('/packages/add', ['reboot' => 9] + $base);
        $this->assertResponseContains('reboot attribute is invalid');

        $this->post('/packages/add', ['id_text' => '7zip'] + $base);
        $this->assertResponseContains('That package already exists.');

        $this->post('/packages/add', ['revision' => '%VERSION%'] + $base);
        $this->assertTrue($this->fetchTable('Packages')->exists(['id_text' => 'new']));
    }

    /**
     * A package others depend on can be neither disabled nor deleted.
     *
     * @return void
     */
    public function testDependencyProtection(): void
    {
        $this->post('/packages/disable/1');
        $this->assertTrue($this->fetchTable('Packages')->get(1)->enabled);
        $this->assertFlashMessageAt(0, "The Package '7-Zip' cannot be disabled because it is depended on by the following package(s): dep-child");

        $this->post('/packages/delete/1');
        $this->assertTrue($this->fetchTable('Packages')->exists(['id' => 1]));

        $this->post('/packages/delete/10');
        $this->assertFalse($this->fetchTable('Packages')->exists(['id' => 10]));
        $this->post('/packages/delete/1');
        $this->assertFalse($this->fetchTable('Packages')->exists(['id' => 1]));
    }

    /**
     * Deleting a package removes its children and spares those of other packages.
     *
     * @return void
     */
    public function testDeleteCascades(): void
    {
        $this->post('/packages/delete/10');
        $this->post('/packages/delete/2');

        $this->assertFalse($this->fetchTable('Packages')->exists(['id' => 2]));
        $this->assertFalse($this->fetchTable('PackageActions')->exists(['package_id' => 2]));
        $this->assertFalse($this->fetchTable('PackageChecks')->exists(['package_id' => 2]));
        $this->assertFalse($this->fetchTable('ExitCodes')->exists(['package_action_id' => 4]));
        $this->assertTrue($this->fetchTable('PackageChecks')->exists(['package_id' => 1]));
    }

    /**
     * Actions move inside their type, without affecting the others.
     *
     * @return void
     */
    public function testActionsMoveWithinTheirType(): void
    {
        $this->post('/package-actions/move/5/up');

        $this->assertRedirect(['controller' => 'Packages', 'action' => 'view', 2]);
        $installs = $this->fetchTable('PackageActions')->find()
            ->where(['package_id' => 2, 'type' => 0])
            ->orderBy(['position' => 'ASC'])->all()->extract('id')->toList();
        $this->assertSame([5, 4], $installs);
    }

    /**
     * Actions are added at the end of their type, edited and deleted.
     *
     * @return void
     */
    public function testActionLifecycle(): void
    {
        $this->post('/package-actions/add/11', ['type' => 0, 'command' => 'second.exe', 'timeout' => '', 'workdir' => '']);
        $action = $this->fetchTable('PackageActions')->find()->where(['command' => 'second.exe'])->firstOrFail();
        $this->assertSame(11, $action->package_id);
        $this->assertSame(2, $action->position);

        $this->post('/package-actions/edit/' . $action->id, ['type' => 0, 'command' => 'renamed.exe', 'timeout' => '60', 'workdir' => 'C:\\']);
        $action = $this->fetchTable('PackageActions')->get($action->id);
        $this->assertSame('renamed.exe', $action->command);
        $this->assertSame(60, $action->timeout);

        $this->post('/package-actions/add/11', ['type' => 0, 'command' => '', 'timeout' => 'x']);
        $this->assertResponseContains('The package action command attribute is invalid.');

        $this->post('/package-actions/delete/' . $action->id);
        $this->assertFalse($this->fetchTable('PackageActions')->exists(['id' => $action->id]));
    }

    /**
     * Exit codes accept integers and `any`, once per action.
     *
     * @return void
     */
    public function testExitCodes(): void
    {
        $this->post('/exit-codes/add/5', ['code' => 'ANY', 'reboot' => 1]);
        $this->assertRedirect(['controller' => 'PackageActions', 'action' => 'view', 5]);

        $this->post('/exit-codes/add/5', ['code' => '1', 'reboot' => 0]);
        $this->assertResponseContains('already exists for this package action');

        $this->post('/exit-codes/add/5', ['code' => '1.5', 'reboot' => 0]);
        $this->assertResponseContains('must be an integer');

        $this->post('/exit-codes/edit/5', ['code' => '2', 'reboot' => 3]);
        $this->assertSame('2', $this->fetchTable('ExitCodes')->get(5)->code);

        $this->post('/exit-codes/delete/5');
        $this->assertFalse($this->fetchTable('ExitCodes')->exists(['id' => 5]));
    }

    /**
     * New checks land in the requested branch of the tree of their package.
     *
     * @return void
     */
    public function testCheckAddedInsideLogicalParent(): void
    {
        $this->post('/package-checks/add/2', ['type' => 2, 'condition' => 7, 'path' => 'C:\\new.txt', 'parent_id' => 9]);

        $check = $this->fetchTable('PackageChecks')->find()->where(['path' => 'C:\\new.txt'])->firstOrFail();
        $this->assertSame(9, $check->parent_id);
        $this->assertSame(2, $check->package_id);

        $this->get('/packages/view/2.xml');
        $this->assertMatchesRegularExpression(
            '#<check type="logical" condition="not">.*path="C:\\\\new.txt"/>\s*</check>#s',
            (string)$this->_response->getBody(),
        );
    }

    /**
     * Root checks of two packages do not interfere, despite the shared legacy numbering.
     *
     * @return void
     */
    public function testCheckMovesStayInsidePackage(): void
    {
        $rootChecks = fn(int $packageId): array => $this->fetchTable('PackageChecks')->find()
            ->where(['package_id' => $packageId, 'parent_id IS' => null])
            ->orderBy(['lft' => 'ASC'])->all()->extract('id')->toList();

        $this->post('/package-checks/move/29/top');
        $this->assertSame(29, $rootChecks(2)[0]);
        $this->assertSame([1], $rootChecks(1));

        $this->post('/package-checks/move/29/down');
        $this->assertSame([2, 29, 18], array_slice($rootChecks(2), 0, 3));

        $this->post('/package-checks/move/2/bottom');
        $this->assertSame(2, $rootChecks(2)[array_key_last($rootChecks(2))]);

        // The logical subtree moved with its root.
        $this->get('/packages/view/2.xml');
        $this->assertMatchesRegularExpression(
            '#condition="and">\s*<check type="registry" condition="exists"#',
            (string)$this->_response->getBody(),
        );
    }

    /**
     * Deleting a logical check removes its descendants, not its neighbours.
     *
     * @return void
     */
    public function testDeleteCheckRemovesSubtree(): void
    {
        $this->post('/package-checks/delete/5');

        $this->assertFalse($this->fetchTable('PackageChecks')->exists(['id' => 9]));
        $this->assertFalse($this->fetchTable('PackageChecks')->exists(['id' => 12]));
        $this->assertTrue($this->fetchTable('PackageChecks')->exists(['id' => 3]));
        $this->assertTrue($this->fetchTable('PackageChecks')->exists(['id' => 1]));
    }

    /**
     * Editing a logical check keeps it in place, and its count reaches the feed.
     *
     * @return void
     */
    public function testLogicalCheckEditKeepsParent(): void
    {
        $this->post('/package-checks/edit/14', ['type' => 0, 'condition' => 3, 'value' => '1']);

        $check = $this->fetchTable('PackageChecks')->get(14);
        $this->assertSame(2, $check->parent_id);
        $this->assertSame('1', $check->value);
    }

    /**
     * Atleast and atmost need the count wpkg.js requires.
     *
     * @return void
     */
    public function testLogicalCountIsRequired(): void
    {
        $this->post('/package-checks/add/2', ['type' => 0, 'condition' => 3, 'value' => '', 'parent_id' => 0]);
        $this->assertResponseContains('need a count');

        $this->post('/package-checks/add/2', ['type' => 0, 'condition' => 4, 'value' => 'x', 'parent_id' => 0]);
        $this->assertResponseContains('need a count');

        $this->post('/package-checks/add/2', ['type' => 0, 'condition' => 1, 'parent_id' => 0]);
        $this->assertRedirect(['controller' => 'Packages', 'action' => 'view', 2]);
    }

    /**
     * The parent list offers the root explicitly.
     *
     * @return void
     */
    public function testParentListShowsRootNode(): void
    {
        $this->get('/package-checks/add/2');

        $this->assertResponseContains('<option value="0">Root Node</option>');
        $this->assertResponseContains('-Logical And');
    }

    /**
     * A condition of another type is rejected.
     *
     * @return void
     */
    public function testCheckConditionMustMatchType(): void
    {
        $this->post('/package-checks/add/2', ['type' => 2, 'condition' => 5, 'path' => 'x', 'parent_id' => 0]);

        $this->assertResponseContains('The package check condition attribute is invalid.');
        $this->assertFalse($this->fetchTable('PackageChecks')->exists(['path' => 'x']));
    }
}
