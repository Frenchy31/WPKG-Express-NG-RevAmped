<?php
/**
 * @var \App\View\AppView $this
 */
?>
<!DOCTYPE html>
<html lang="<?= $this->language()->value ?>"<?= $this->theme()->htmlAttribute() ?>>
<head>
    <?= $this->Html->charset() ?>
    <meta name="color-scheme" content="light dark">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>wpkgExpress-ng :: <?= h($this->fetch('title')) ?></title>
    <?= $this->Html->css('plain') ?>
    <?= $this->fetch('css') ?>
</head>
<body>
<div id="siteBox">
    <div id="headerBox">
        <div id="headerLeft">
            <?= $this->Html->link('wpkgExpress-ng', '/', ['title' => __('Return to main summary page')]) ?>
            <div class="small"><?= __('A web-based frontend to {0}', $this->Html->link('WPKG', 'https://wpkg.org', ['title' => __('Official home for the WPKG project')])) ?></div>
        </div>
        <div id="headerRight">
            <nav class="headerNav">
                <?= $this->Html->link($this->Wpkg->symbol('packages') . __('Packages'), '/packages', ['escape' => false, 'title' => __('Manage packages and associated information'), 'class' => 'packages ' . $this->Wpkg->navigationClass('/packages')]) ?>
                <?= $this->Html->link($this->Wpkg->symbol('profiles') . __('Profiles'), '/profiles', ['escape' => false, 'title' => __('Manage profiles'), 'class' => 'profiles ' . $this->Wpkg->navigationClass('/profiles')]) ?>
                <?= $this->Html->link($this->Wpkg->symbol('hosts') . __('Hosts'), '/hosts', ['escape' => false, 'title' => __('Manage hosts'), 'class' => 'hosts ' . $this->Wpkg->navigationClass('/hosts')]) ?>
                <?= $this->Html->link($this->Wpkg->symbol('tick') . __('Reports'), '/reports', ['escape' => false, 'title' => __('Review the last run of each workstation'), 'class' => 'reports ' . $this->Wpkg->navigationClass('/reports')]) ?>
                <?= $this->Html->link($this->Wpkg->symbol('admin') . __('Admin'), '/admin', ['escape' => false, 'title' => __('Perform other administrative tasks'), 'class' => 'admin ' . $this->Wpkg->navigationClass('/admin')]) ?>
                <?= $this->Form->postLink($this->Wpkg->symbol('logout') . __('Logout'), ['controller' => 'Auth', 'action' => 'logout'], ['escapeTitle' => false, 'title' => __('Log out of the current session'), 'class' => 'navLink logout']) ?>
            </nav>
            <div class="headerSearch">
                <?= $this->Form->create(null, ['type' => 'get', 'url' => '/search']) ?>
                <?= $this->Form->select('type', ['all' => __('All'), 'packages' => __('Packages'), 'profiles' => __('Profiles'), 'hosts' => __('Hosts')], ['class' => 'input', 'value' => $this->getRequest()->getQuery('type', 'all')]) ?>
                <?= $this->Form->text('q', ['class' => 'input', 'size' => 15, 'value' => $this->getRequest()->getQuery('q')]) ?>
                <?= $this->Form->button(__('Search')) ?>
                <?= $this->Form->end() ?>
            </div>
        </div>
    </div>

    <div class="bar"></div>
    <div id="content">
        <?= $this->Flash->render() ?>
        <?= $this->fetch('content') ?>
    </div>

    <div id="footerBox">
        <div id="footerLeft"><?= $this->element('theme_switch') ?>
            <?= $this->element('language_switch') ?></div>
        <div id="footerRight">
            <?= __('&copy; 2026 Aubin Puyoou, based on wpkgExpress-ng (&copy; 2013 {0}) and wpkgExpress (&copy; 2009 Brian White).', $this->Html->link('Probesys', 'https://www.probesys.com', ['title' => __('Probesys - Open Source specialist')])) ?>
            <?= __('Free software under the {0}, without any warranty.', $this->Html->link('GPL-3.0', 'https://www.gnu.org/licenses/gpl-3.0.html', ['title' => __('GNU General Public License, version 3 or later')])) ?>
        </div>
    </div>
</div>
</body>
</html>
