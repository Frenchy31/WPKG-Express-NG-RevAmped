<?php
/**
 * @var \App\View\AppView $this
 * @var string|null $notes Notes.
 */
echo empty($notes) ? h(__('<None>')) : '<div class="notes">' . nl2br(h($notes)) . '</div>';
