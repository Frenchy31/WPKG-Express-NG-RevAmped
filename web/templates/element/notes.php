<?php
/**
 * @var \App\View\AppView $this
 * @var string|null $notes Notes.
 */
echo empty($notes) ? '&lt;None&gt;' : '<div class="notes">' . nl2br(h($notes)) . '</div>';
