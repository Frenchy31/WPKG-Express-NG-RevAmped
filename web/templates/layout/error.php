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
    <title>wpkgExpress-ng :: <?= __('Error') ?></title>
    <?= $this->Html->css('plain') ?>
</head>
<body>
<div id="siteBox">
    <div id="content" class="narrow">
        <?= $this->fetch('content') ?>
        <p><?= $this->Html->link(__('Back to the summary'), '/') ?></p>
    </div>
</div>
</body>
</html>
