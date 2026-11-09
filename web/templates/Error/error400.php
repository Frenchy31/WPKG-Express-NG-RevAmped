<?php
/**
 * @var \App\View\AppView $this
 * @var string $message
 */
$this->setLayout('error');
?>
<?php
$knownMessages = [
    'Not Found' => __('Not Found'),
    'Bad Request' => __('Bad Request'),
    'Forbidden' => __('Forbidden'),
    'Method Not Allowed' => __('Method Not Allowed'),
    'CSRF token mismatch.' => __('CSRF token mismatch.'),
];
?>
<h2><?= h($knownMessages[$message] ?? $message) ?></h2>
