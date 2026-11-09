<?php
/**
 * @var \App\View\AppView $this
 */
?>
<p class="counter"><?= $this->Paginator->counter(__('Showing page {{page}} of {{pages}}, {{current}} record(s) out of {{count}} total')) ?></p>
<?php if ($this->Paginator->hasPrev() || $this->Paginator->hasNext()) : ?>
    <div class="paging">
        <?= $this->Paginator->prev(__('<< previous')) ?>
        | <?= $this->Paginator->numbers() ?>
        | <?= $this->Paginator->next(__('next >>')) ?>
    </div>
<?php endif; ?>
