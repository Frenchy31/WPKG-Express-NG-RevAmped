<?php
/**
 * @var \App\View\AppView $this
 */

use App\View\Language;

$current = $this->language();
?>
<?= $this->Form->create(null, ['url' => '/language']) ?>
    <span><?= __('Language:') ?></span>
    <?php foreach (Language::cases() as $language) : ?>
        <?= $this->Form->button($language->label(), [
            'type' => 'submit',
            'name' => 'lang',
            'value' => $language->value,
            'aria-pressed' => $language === $current ? 'true' : 'false',
        ]) ?>
    <?php endforeach; ?>
<?= $this->Form->end() ?>
