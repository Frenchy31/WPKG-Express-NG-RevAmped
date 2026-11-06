<?php
/**
 * @var \App\View\AppView $this
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?= $this->Html->charset() ?>
    <title>wpkgExpress-ng :: Login</title>
    <?= $this->Html->css(['plain', 'installer']) ?>
</head>
<body>
<div id="siteBox">
    <div id="content">
        <?= $this->Flash->render() ?>
        <?= $this->fetch('content') ?>
    </div>
</div>
</body>
</html>
