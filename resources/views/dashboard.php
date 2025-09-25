<?php
/** @var string $title */
/** @var string $subtitle */
/** @var string $email */
?>
<?php ob_start(); ?>
<div class="space-y-6">
    <div class="space-y-2">
        <h2 class="text-xl font-semibold">Welcome back, <?= htmlspecialchars($email, ENT_QUOTES) ?></h2>
        <p class="text-slate-300">You're signed in to job.smeird.com. Use the quick links below to manage your security.</p>
    </div>
    <div class="space-y-3">
        <a href="/auth/backup-codes" class="block w-full rounded-md bg-indigo-600 hover:bg-indigo-500 px-4 py-3 text-center font-semibold">Generate backup codes</a>
        <form method="post" action="/auth/logout">
            <button type="submit" class="w-full rounded-md border border-slate-600 hover:bg-slate-700 px-4 py-2 text-center font-semibold">Sign out</button>
        </form>
    </div>
</div>
<?php $body = ob_get_clean(); ?>
<?php include __DIR__ . '/layout.php'; ?>
