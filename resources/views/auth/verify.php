<?php
/** @var string $title */
/** @var string $subtitle */
/** @var string $actionUrl */
/** @var string $buttonLabel */
/** @var string|null $error */
/** @var string|null $status */
/** @var string|null $email */
/** @var string|null $resendUrl */
/** @var string|null $resendLabel */
?>
<?php ob_start(); ?>
<form method="post" action="<?= htmlspecialchars($actionUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="space-y-4">
    <input type="hidden" name="_token" value="<?= htmlspecialchars((string) $csrfToken, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
    <input type="hidden" name="email" value="<?= htmlspecialchars($email ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
    <div>
        <label for="code" class="block text-sm font-medium text-slate-200">6-digit code from your QR scan</label>
        <input id="code" name="code" inputmode="numeric" minlength="6" maxlength="6" pattern="[0-9]{6}" required class="mt-1 block w-full rounded-md bg-slate-900 border border-slate-700 px-3 py-2 text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500">
    </div>
    <?php if (!empty($error)) : ?>
        <div class="rounded-md bg-red-900/40 border border-red-500 px-3 py-2 text-sm text-red-200">
            <?= htmlspecialchars($error, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
        </div>
    <?php endif; ?>
    <?php if (!empty($status)) : ?>
        <div class="rounded-md bg-emerald-900/40 border border-emerald-500 px-3 py-2 text-sm text-emerald-200">
            <?= htmlspecialchars($status, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
        </div>
    <?php endif; ?>
    <button type="submit" class="w-full rounded-md bg-indigo-600 hover:bg-indigo-500 px-4 py-2 text-center font-semibold"><?= htmlspecialchars($buttonLabel, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></button>
</form>
<?php $resendHref = $resendUrl ?? '/auth/login'; ?>
<div class="text-center text-sm text-slate-400">
    <a href="<?= htmlspecialchars($resendHref, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="hover:text-indigo-300">
        <?= htmlspecialchars($resendLabel ?? 'Request a new QR code', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
    </a>
</div>
<?php $body = ob_get_clean(); ?>
<?php include __DIR__ . '/../layout.php'; ?>
