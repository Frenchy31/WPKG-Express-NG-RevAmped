<?php
/**
 * @var \App\View\AppView $this
 */

use App\View\Theme;

$current = $this->theme();
?>
<?= $this->Form->create(null, ['url' => '/theme']) ?>
    <span>Theme:</span>
    <?php foreach (Theme::cases() as $theme) : ?>
        <?= $this->Form->button($theme->label(), [
            'type' => 'submit',
            'name' => 'theme',
            'value' => $theme->value,
            'aria-pressed' => $theme === $current ? 'true' : 'false',
        ]) ?>
    <?php endforeach; ?>
<?= $this->Form->end() ?>
