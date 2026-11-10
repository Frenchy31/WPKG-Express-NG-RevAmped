<?php
/**
 * Time of the last indexing pass, and a warning when the indexer stopped or fails.
 *
 * @var \App\View\AppView $this
 * @var \App\Report\IndexState|null $indexState
 * @var bool $indexOverdue
 */
?>
<?php if ($indexState === null) : ?>
    <div class="message warning"><?= __('The reports indexer has never run: start the reports-indexer service.') ?></div>
<?php else : ?>
    <?php if ($indexState->error !== null) : ?>
        <div class="message warning"><?= h(__('The last indexing failed: {0}', $indexState->error)) ?></div>
    <?php endif; ?>
    <?php if ($indexOverdue) : ?>
        <div class="message warning"><?= h(__('The reports indexer has not run since {0}: the reports below may be out of date.', $this->Wpkg->date($indexState->finishedAt))) ?></div>
    <?php endif; ?>
    <p class="report-note"><?= h(__('Last indexing: {0}', $this->Wpkg->date($indexState->finishedAt))) ?></p>
<?php endif; ?>
