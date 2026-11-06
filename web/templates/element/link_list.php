<?php
/**
 * @var \App\View\AppView $this
 * @var array<string, array<string, mixed>> $links Label => URL.
 */
if (!$links) {
    echo '&lt;None&gt;';

    return;
}
?>
<ul>
    <?php foreach ($links as $label => $url) : ?>
        <li><?= $this->Html->link($label, $url) ?></li>
    <?php endforeach; ?>
</ul>
