<?php
/**
 * @var \App\View\AppView $this
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?= $this->Html->charset() ?>
    <title>wpkgExpress-ng :: Error</title>
    <?= $this->Html->css('plain') ?>
</head>
<body>
<div id="siteBox">
    <div id="content">
        <?= $this->fetch('content') ?>
        <p><?= $this->Html->link('Back to the summary', '/') ?></p>
    </div>
</div>
</body>
</html>
