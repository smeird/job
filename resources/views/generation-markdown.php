<?php
/** @var array<string, mixed> $viewer */
/** @var string $title */
/** @var string $subtitle */
/** @var array<int, array{href: string, label: string, current: bool}> $navLinks */
?>
<?php ob_start(); ?>
<?php include __DIR__ . '/partials/markdown-viewer.php'; ?>
<?php $body = ob_get_clean(); ?>
<?php include __DIR__ . '/layout.php'; ?>
