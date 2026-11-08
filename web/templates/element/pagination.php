<?php
/**
 * @var \App\View\AppView $this
 */
?>
<p class="counter"><?= $this->Paginator->counter('Showing page {{page}} of {{pages}}, {{current}} record(s) out of {{count}} total') ?></p>
<?php if ($this->Paginator->hasPrev() || $this->Paginator->hasNext()) : ?>
    <div class="paging">
        <?= $this->Paginator->prev('<< previous') ?>
        | <?= $this->Paginator->numbers() ?>
        | <?= $this->Paginator->next('next >>') ?>
    </div>
<?php endif; ?>
