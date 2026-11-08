<?php

declare(strict_types=1);

namespace App\Test\TestCase\View\Helper;

use App\View\AppView;
use App\View\Helper\WpkgHelper;
use Cake\Http\ServerRequest;
use Cake\TestSuite\TestCase;

/**
 * Icon rendering of the WPKG helper.
 */
class WpkgHelperTest extends TestCase
{
    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->loadRoutes();
    }

    /**
     * Builds a view and returns its WPKG helper.
     *
     * @return \App\View\Helper\WpkgHelper
     */
    private function buildHelper(): WpkgHelper
    {
        $view = new AppView(new ServerRequest(['url' => '/packages']));

        return $view->Wpkg;
    }

    /**
     * An icon links to its page and takes its symbol from the sprite.
     *
     * @return void
     */
    public function testIconLinksToSpriteSymbol(): void
    {
        $html = $this->buildHelper()->icon('pencil', ['controller' => 'Packages', 'action' => 'edit', 3], 'Edit');

        $this->assertStringContainsString('title="Edit"', $html);
        $this->assertStringContainsString('aria-label="Edit"', $html);
        $this->assertStringContainsString('class="icon-link icon-pencil"', $html);
        $this->assertStringContainsString('<use href="/img/icons.svg#pencil"/>', $html);
        $this->assertStringNotContainsString('.png', $html);
    }

    /**
     * An icon changing data is a POST form link with its confirmation.
     *
     * @return void
     */
    public function testPostIconKeepsConfirmation(): void
    {
        $html = $this->buildHelper()->postIcon(
            'delete',
            ['controller' => 'Packages', 'action' => 'delete', 3],
            'Delete',
            "Delete Package 'x'?",
        );

        $this->assertStringContainsString('method="post"', $html);
        $this->assertStringContainsString('title="Delete"', $html);
        $this->assertStringContainsString('icons.svg#delete', $html);
        $this->assertStringContainsString('Delete Package', $html);
    }

    /**
     * The four move buttons keep their order and titles.
     *
     * @return void
     */
    public function testMoveIconsOrder(): void
    {
        $html = $this->buildHelper()->moveIcons(['controller' => 'Hosts', 'action' => 'move', 4]);

        $this->assertMatchesRegularExpression('/Move top.*Move up.*Move down.*Move bottom/s', $html);
        $this->assertStringContainsString('icons.svg#go-bottom', $html);
    }

    /**
     * The sprite defines every symbol the templates use.
     *
     * @return void
     */
    public function testSpriteDefinesUsedSymbols(): void
    {
        $sprite = (string)file_get_contents(WWW_ROOT . 'img' . DS . 'icons.svg');
        $names = [
            'add', 'pencil', 'delete', 'go-top', 'go-up', 'go-down', 'go-bottom', 'packages',
            'profiles', 'hosts', 'tick', 'admin', 'logout', 'var', 'error',
        ];

        foreach ($names as $name) {
            $this->assertStringContainsString('<symbol id="' . $name . '"', $sprite);
        }
    }
}
