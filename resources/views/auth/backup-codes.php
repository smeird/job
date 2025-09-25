<?php
/** @var string $title */
/** @var string $subtitle */
/** @var array $codes */
?>
<?php ob_start(); ?>
<div class="space-y-4">
    <div class="space-y-2 text-sm text-slate-300">
        <p>Store these one-time codes somewhere safe. Each code can only be used once.</p>
        <p class="font-medium text-amber-300">They will not be shown again.</p>
    </div>
    <div class="grid grid-cols-2 gap-3">
        <?php foreach ($codes as $code): ?>
            <div class="rounded-md bg-slate-900 border border-slate-700 px-3 py-2 text-center text-lg font-semibold tracking-widest"><?= htmlspecialchars($code, ENT_QUOTES) ?></div>
        <?php endforeach; ?>
    </div>
    <a href="/" class="block w-full rounded-md bg-indigo-600 hover:bg-indigo-500 px-4 py-2 text-center font-semibold">Return to dashboard</a>
</div>
<?php $body = ob_get_clean(); ?>
<?php include __DIR__ . '/../layout.php'; ?>
