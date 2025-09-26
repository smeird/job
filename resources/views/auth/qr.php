<?php

use DateTimeInterface;

/** @var string $title */
/** @var string $subtitle */
/** @var string $actionUrl */
/** @var string $buttonLabel */
/** @var string|null $email */
/** @var string $code */
/** @var string $instructions */
/** @var DateTimeInterface $expiresAt */
/** @var string $resendUrl */
/** @var string $resendLabel */
/** @var string|null $csrfToken */

$groupedCode = trim(chunk_split($code, 3, ' '));
?>
<?php ob_start(); ?>
<div class="space-y-6">
    <div class="space-y-3 text-center">
        <div
            id="qr-code"
            class="mx-auto h-48 w-48 rounded-lg bg-white p-2 shadow-inner"
            role="img"
            aria-label="QR code containing your one-time 6-digit passcode"
        ></div>
        <p class="text-sm text-slate-300">
            <?= htmlspecialchars($instructions, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
        </p>
        <p class="text-xs uppercase tracking-widest text-slate-500">
            Expires at <?= htmlspecialchars($expiresAt->format('H:i T'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
        </p>
        <p class="text-sm text-slate-200">
            Can't scan? Enter this code manually:
            <span class="font-semibold tracking-widest text-indigo-300">
                <?= htmlspecialchars($groupedCode, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
            </span>
        </p>
    </div>
    <form method="post" action="<?= htmlspecialchars($actionUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="space-y-4">
        <input type="hidden" name="_token" value="<?= htmlspecialchars((string) $csrfToken, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
        <input type="hidden" name="email" value="<?= htmlspecialchars($email ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
        <div>
            <label for="code" class="block text-sm font-medium text-slate-200">6-digit code</label>
            <input
                id="code"
                name="code"
                inputmode="numeric"
                minlength="6"
                maxlength="6"
                pattern="[0-9]{6}"
                required
                class="mt-1 block w-full rounded-md bg-slate-900 border border-slate-700 px-3 py-2 text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500"
            >
        </div>
        <button type="submit" class="w-full rounded-md bg-indigo-600 hover:bg-indigo-500 px-4 py-2 text-center font-semibold">
            <?= htmlspecialchars($buttonLabel, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
        </button>
    </form>
    <div class="text-center text-sm text-slate-400">
        <a href="<?= htmlspecialchars($resendUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="hover:text-indigo-300">
            <?= htmlspecialchars($resendLabel, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
        </a>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        var qrContainer = document.getElementById('qr-code');
        if (!qrContainer) {
            return;
        }

        if (typeof QRCode === 'undefined') {
            qrContainer.classList.add('flex', 'items-center', 'justify-center', 'bg-slate-800', 'text-slate-300', 'text-xs');
            qrContainer.textContent = 'QR code unavailable';
            return;
        }

        new QRCode(qrContainer, {
            text: <?= json_encode($code, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
            width: 192,
            height: 192,
            colorDark: '#0f172a',
            colorLight: '#ffffff',
            correctLevel: QRCode.CorrectLevel.M
        });
    });
</script>
<?php $body = ob_get_clean(); ?>
<?php include __DIR__ . '/../layout.php'; ?>
