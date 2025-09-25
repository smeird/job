<?php
/** @var string $title */
/** @var string $subtitle */
/** @var string|null $error */
?>
<?php ob_start(); ?>
<div class="space-y-4">
    <p class="text-slate-300 text-sm">Generate new backup codes to access your account if email is unavailable.</p>
    <?php if (!empty($error)) : ?>
        <div class="rounded-md bg-red-900/40 border border-red-500 px-3 py-2 text-sm text-red-200">
            <?= htmlspecialchars($error, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
        </div>
    <?php endif; ?>
    <form method="post" action="/auth/backup-codes" class="space-y-4">
        <input type="hidden" name="_token" value="<?= htmlspecialchars((string) $csrfToken, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
        <button type="submit" class="w-full rounded-md bg-indigo-600 hover:bg-indigo-500 px-4 py-2 text-center font-semibold">Generate backup codes</button>
    </form>
    <a href="/" class="block w-full rounded-md border border-slate-600 hover:bg-slate-700 px-4 py-2 text-center font-semibold">Return to dashboard</a>
</div>
<?php $body = ob_get_clean(); ?>
<?php include __DIR__ . '/../layout.php'; ?>
