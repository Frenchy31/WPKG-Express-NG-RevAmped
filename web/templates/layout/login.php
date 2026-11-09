<?php
/**
 * @var \App\View\AppView $this
 */
?>
<!DOCTYPE html>
<html lang="en"<?= $this->theme()->htmlAttribute() ?>>
<head>
    <?= $this->Html->charset() ?>
    <meta name="color-scheme" content="light dark">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>wpkgExpress-ng :: Login</title>
    <?= $this->Html->css('plain') ?>
</head>
<body>
<div id="siteBox">
    <div id="content" class="narrow">
        <?= $this->Flash->render() ?>
        <?= $this->fetch('content') ?>
    </div>
</div>
</body>
</html>
