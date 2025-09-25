<?php
/** @var string $title */
?>
<?php ob_start(); ?>
<div class="space-y-6 text-center">
    <div class="space-y-2">
        <h2 class="text-3xl font-bold">A focused workspace for your next role</h2>
        <p class="text-slate-300">Securely manage your applications, notes, and interview prep after you sign in.</p>
    </div>
    <div class="space-y-3">
        <a href="/auth/login" class="block w-full rounded-md bg-indigo-600 hover:bg-indigo-500 px-4 py-3 font-semibold">Sign in</a>
        <a href="/auth/register" class="block w-full rounded-md border border-slate-600 hover:bg-slate-700 px-4 py-3 font-semibold">Create an account</a>
    </div>
</div>
<?php $body = ob_get_clean(); ?>
<?php include __DIR__ . '/layout.php'; ?>
