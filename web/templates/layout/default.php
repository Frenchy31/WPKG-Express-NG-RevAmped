<?php
/**
 * @var \App\View\AppView $this
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?= $this->Html->charset() ?>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>wpkgExpress-ng :: <?= h($this->fetch('title')) ?></title>
    <?= $this->Html->css('plain') ?>
    <?= $this->fetch('css') ?>
</head>
<body>
<div id="siteBox">
    <div id="headerBox">
        <div id="headerLeft">
            <?= $this->Html->link('wpkgExpress-ng', '/', ['title' => 'Return to main summary page']) ?>
            <div class="small">A web-based frontend to <?= $this->Html->link('WPKG', 'https://wpkg.org', ['title' => 'Official home for the WPKG project']) ?></div>
        </div>
        <div id="headerRight">
            <nav class="headerNav">
                <?= $this->Html->link($this->Wpkg->symbol('packages') . 'Packages', '/packages', ['escape' => false, 'title' => 'Manage packages and associated information', 'class' => 'packages ' . $this->Wpkg->navigationClass('/packages')]) ?>
                <?= $this->Html->link($this->Wpkg->symbol('profiles') . 'Profiles', '/profiles', ['escape' => false, 'title' => 'Manage profiles', 'class' => 'profiles ' . $this->Wpkg->navigationClass('/profiles')]) ?>
                <?= $this->Html->link($this->Wpkg->symbol('hosts') . 'Hosts', '/hosts', ['escape' => false, 'title' => 'Manage hosts', 'class' => 'hosts ' . $this->Wpkg->navigationClass('/hosts')]) ?>
                <?= $this->Html->link($this->Wpkg->symbol('tick') . 'Reports', '/reports', ['escape' => false, 'title' => 'Review the last run of each workstation', 'class' => 'reports ' . $this->Wpkg->navigationClass('/reports')]) ?>
                <?= $this->Html->link($this->Wpkg->symbol('admin') . 'Admin', '/admin', ['escape' => false, 'title' => 'Perform other administrative tasks', 'class' => 'admin ' . $this->Wpkg->navigationClass('/admin')]) ?>
                <?= $this->Form->postLink($this->Wpkg->symbol('logout') . 'Logout', ['controller' => 'Auth', 'action' => 'logout'], ['escapeTitle' => false, 'title' => 'Log out of the current session', 'class' => 'navLink logout']) ?>
            </nav>
            <div class="headerSearch">
                <?= $this->Form->create(null, ['type' => 'get', 'url' => '/search']) ?>
                <?= $this->Form->select('type', ['all' => 'All', 'packages' => 'Packages', 'profiles' => 'Profiles', 'hosts' => 'Hosts'], ['class' => 'input', 'value' => $this->getRequest()->getQuery('type', 'all')]) ?>
                <?= $this->Form->text('q', ['class' => 'input', 'size' => 15, 'value' => $this->getRequest()->getQuery('q')]) ?>
                <?= $this->Form->button('Search') ?>
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
        <div id="footerLeft"></div>
        <div id="footerRight">
            Copyright &copy; 2013 <?= $this->Html->link('Probesys', 'https://www.probesys.com', ['title' => 'Probesys - Open Source specialist']) ?> - Based on wpkgExpress by Brian White
        </div>
    </div>
</div>
</body>
</html>
