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
<?php
$formId = 'form-' . preg_replace('/[^a-z0-9]+/', '-', strtolower(trim((string) $actionUrl, '/')));
if ($formId === 'form-') {
    $formId = 'form-auth-verify';
}
$codeInputId = $formId . '-code';
$resendHref = $resendUrl ?? '/auth/login';
?>
<?php ob_start(); ?>
<section class="space-y-6" aria-labelledby="<?= htmlspecialchars($formId, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>-heading">
    <div class="space-y-2 text-center">
        <h2 id="<?= htmlspecialchars($formId, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>-heading" class="text-2xl font-semibold text-slate-100">
            <?= htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
        </h2>
        <?php if (!empty($subtitle)) : ?>
            <p class="text-sm text-slate-300">
                <?= htmlspecialchars($subtitle, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
            </p>
        <?php endif; ?>
    </div>
    <form
        id="<?= htmlspecialchars($formId, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
        data-site-id="job.smeird.com"
        method="post"
        action="<?= htmlspecialchars($actionUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
        class="space-y-4 rounded-lg border border-slate-800/80 bg-slate-900/70 p-6 shadow-xl"
    >
        <input type="hidden" name="_token" value="<?= htmlspecialchars((string) $csrfToken, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
        <input type="hidden" name="email" value="<?= htmlspecialchars($email ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
        <div class="space-y-2 text-left">
            <label for="<?= htmlspecialchars($codeInputId, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="block text-sm font-medium text-slate-200">
                6-digit code from your QR scan
            </label>
            <input
                id="<?= htmlspecialchars($codeInputId, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
                name="code"
                inputmode="numeric"
                minlength="6"
                maxlength="6"
                pattern="[0-9]{6}"
                autocomplete="one-time-code"
                required
                class="mt-1 block w-full rounded-md border border-slate-700 bg-slate-950/60 px-3 py-2 text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500"
            >
            <p class="text-xs text-slate-400">Enter the passcode shown in your authenticator or QR scan.</p>
        </div>
        <?php if (!empty($error)) : ?>
            <div class="rounded-md border border-red-500 bg-red-900/40 px-3 py-2 text-sm text-red-200" role="alert">
                <?= htmlspecialchars($error, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
            </div>
        <?php endif; ?>
        <?php if (!empty($status)) : ?>
            <div class="rounded-md border border-emerald-500 bg-emerald-900/40 px-3 py-2 text-sm text-emerald-200" role="status">
                <?= htmlspecialchars($status, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
            </div>
        <?php endif; ?>
        <button type="submit" class="w-full rounded-md bg-indigo-600 px-4 py-2 text-center font-semibold text-white transition hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-400">
            <?= htmlspecialchars($buttonLabel, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
        </button>
    </form>
    <div class="text-center text-sm text-slate-400">
        <a href="<?= htmlspecialchars($resendHref, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="font-medium text-indigo-300 transition hover:text-indigo-200">
            <?= htmlspecialchars($resendLabel ?? 'Request a new QR code', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
        </a>
    </div>
</section>
<?php $body = ob_get_clean(); ?>
<?php include __DIR__ . '/../layout.php'; ?>
