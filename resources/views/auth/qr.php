<?php

use DateTimeInterface;

/** @var string $title */
/** @var string $subtitle */
/** @var string $actionUrl */
/** @var string $buttonLabel */
/** @var string|null $email */
/** @var string $code */
/** @var string $totpSecret */
/** @var string $qrValue */
/** @var string $instructions */
/** @var DateTimeInterface $expiresAt */
/** @var string $resendUrl */
/** @var string $resendLabel */
/** @var string|null $csrfToken */

$groupedCode = trim(chunk_split($code, 3, ' '));
$formattedSecret = trim(chunk_split(strtoupper($totpSecret), 4, ' '));
?>
<?php
$formId = 'form-' . preg_replace('/[^a-z0-9]+/', '-', strtolower(trim((string) $actionUrl, '/')));
if ($formId === 'form-') {
    $formId = 'form-auth-qr';
}
$codeInputId = $formId . '-code';
?>
<?php ob_start(); ?>
<section class="space-y-6" aria-labelledby="<?= htmlspecialchars($formId, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>-heading">
    <div class="space-y-3 text-center">
        <h2 id="<?= htmlspecialchars($formId, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>-heading" class="text-2xl font-semibold text-slate-100">
            <?= htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
        </h2>
        <?php if (!empty($subtitle)) : ?>
            <p class="text-sm text-slate-300">
                <?= htmlspecialchars($subtitle, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
            </p>
        <?php endif; ?>
        <div
            id="qr-code"
            class="mx-auto flex h-48 w-48 items-center justify-center rounded-lg bg-white p-2 shadow-inner"
            role="img"
            aria-live="polite"
            aria-label="QR code containing your one-time 6-digit passcode"
            data-qr-code="<?= htmlspecialchars($qrValue, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
        >
            <span class="text-xs font-medium text-slate-600">Loading QR…</span>
        </div>
        <p class="text-sm text-slate-300">
            <?= htmlspecialchars($instructions, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
        </p>
        <p class="text-xs uppercase tracking-widest text-slate-500">
            Expires at <?= htmlspecialchars($expiresAt->format('H:i T'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
        </p>
        <div class="space-y-2">
            <p class="text-sm text-slate-200">
                Can't scan? Enter this 6-digit code before it expires:
                <span class="font-semibold tracking-widest text-indigo-300">
                    <?= htmlspecialchars($groupedCode, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                </span>
            </p>
            <p class="text-xs text-slate-400">
                Adding it to an authenticator or password manager? Use this setup key:
                <span class="font-mono tracking-widest text-indigo-200">
                    <?= htmlspecialchars($formattedSecret, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                </span>
            </p>
        </div>
    </div>
    <form
        id="<?= htmlspecialchars($formId, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
        data-site-id="job.smeird.com"
        method="post"
        action="<?= htmlspecialchars($actionUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
        class="space-y-4 rounded-lg border border-slate-800/80 bg-slate-900/70 p-6 text-left shadow-xl"
    >
        <input type="hidden" name="_token" value="<?= htmlspecialchars((string) $csrfToken, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
        <input type="hidden" name="email" value="<?= htmlspecialchars($email ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
        <div class="space-y-2">
            <label for="<?= htmlspecialchars($codeInputId, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="block text-sm font-medium text-slate-200">6-digit code</label>
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
        </div>
        <button type="submit" class="w-full rounded-md bg-indigo-600 px-4 py-2 text-center font-semibold text-white transition hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-400">
            <?= htmlspecialchars($buttonLabel, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
        </button>
    </form>
    <div class="text-center text-sm text-slate-400">
        <a href="<?= htmlspecialchars($resendUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="font-medium text-indigo-300 transition hover:text-indigo-200">
            <?= htmlspecialchars($resendLabel, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
        </a>
    </div>
</section>
<script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js" defer></script>
<script src="/assets/js/auth-qr.js" defer></script>
<?php $body = ob_get_clean(); ?>
<?php include __DIR__ . '/../layout.php'; ?>
