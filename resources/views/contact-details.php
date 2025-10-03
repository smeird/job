<?php
/** @var string $title */
/** @var string $subtitle */
/** @var array<string, mixed>|null $details */
/** @var array<int, string> $errors */
/** @var string|null $status */
/** @var array<string, string> $oldInput */
/** @var string|null $csrfToken */

$fullWidth = true;
$navLinks = $navLinks ?? [];
$addressValue = $oldInput['address'] ?? ($details['address'] ?? '');
$phoneValue = $oldInput['phone'] ?? ($details['phone'] ?? '');
$emailValue = $oldInput['email'] ?? ($details['email'] ?? '');
?>
<?php ob_start(); ?>
<div class="space-y-8">
    <header class="space-y-2">
        <p class="text-sm uppercase tracking-[0.3em] text-indigo-400">Personalisation</p>
        <h2 class="text-3xl font-semibold text-white">Contact details for cover letters</h2>
        <p class="max-w-2xl text-sm text-slate-400">
            Save the address and contact information you want the assistant to use when building cover letter headers.
        </p>
    </header>

    <?php if (!empty($status)) : ?>
        <div class="rounded-xl border border-emerald-500/40 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-100">
            <?= htmlspecialchars($status, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($errors)) : ?>
        <div class="space-y-2">
            <?php foreach ($errors as $error) : ?>
                <div class="rounded-xl border border-rose-500/40 bg-rose-500/10 px-4 py-3 text-sm text-rose-100">
                    <?= htmlspecialchars($error, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="grid gap-6 lg:grid-cols-[minmax(0,480px),1fr]">
        <form method="post" action="/profile/contact-details" class="space-y-5 rounded-2xl border border-slate-800/80 bg-slate-900/70 p-6 shadow-xl">
            <input type="hidden" name="_token" value="<?= htmlspecialchars((string) $csrfToken, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">

            <div class="space-y-2">
                <label for="address" class="text-sm font-medium text-slate-200">Home address</label>
                <textarea
                    id="address"
                    name="address"
                    rows="6"
                    class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-200 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-400"
                    required
                ><?= htmlspecialchars($addressValue, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></textarea>
                <p class="text-xs text-slate-500">Include each line exactly as you would like it to appear at the top of your letter.</p>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <div class="space-y-2">
                    <label for="email" class="text-sm font-medium text-slate-200">Contact email (optional)</label>
                    <input
                        type="email"
                        id="email"
                        name="email"
                        value="<?= htmlspecialchars($emailValue, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
                        class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-200 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-400"
                        placeholder="you@example.com"
                    >
                    <p class="text-xs text-slate-500">Leave blank to omit an email address from generated cover letters.</p>
                </div>
                <div class="space-y-2">
                    <label for="phone" class="text-sm font-medium text-slate-200">Phone number (optional)</label>
                    <input
                        type="text"
                        id="phone"
                        name="phone"
                        value="<?= htmlspecialchars($phoneValue, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
                        class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-200 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-400"
                        placeholder="+44 20 7946 0958"
                    >
                    <p class="text-xs text-slate-500">Only digits, spaces, brackets, dashes, and plus signs are accepted.</p>
                </div>
            </div>

            <div class="pt-2">
                <button type="submit" class="w-full rounded-lg bg-indigo-500 px-4 py-2 text-sm font-semibold text-white transition hover:bg-indigo-400">
                    Save contact details
                </button>
            </div>
        </form>

        <aside class="space-y-5 rounded-2xl border border-slate-800/80 bg-slate-900/40 p-6 text-sm text-slate-300">
            <div class="space-y-2">
                <h3 class="text-lg font-semibold text-white">How your details are used</h3>
                <p>The assistant adds your address above the greeting and lists any provided email or phone number beneath it before drafting each cover letter.</p>
            </div>
            <div class="space-y-2">
                <h4 class="text-sm font-semibold text-slate-100">Current saved details</h4>
                <?php if (!empty($details)) : ?>
                    <div class="space-y-1 rounded-lg border border-slate-800/80 bg-slate-900/70 p-4">
                        <p class="whitespace-pre-line text-slate-200"><?= htmlspecialchars((string) ($details['address'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
                        <?php if (!empty($details['email'])) : ?>
                            <p class="text-slate-400">Email: <?= htmlspecialchars((string) $details['email'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
                        <?php endif; ?>
                        <?php if (!empty($details['phone'])) : ?>
                            <p class="text-slate-400">Phone: <?= htmlspecialchars((string) $details['phone'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
                        <?php endif; ?>
                    </div>
                <?php else : ?>
                    <p class="rounded-lg border border-amber-500/30 bg-amber-500/10 p-3 text-amber-100">
                        You have not saved any contact details yet. Add them to personalise cover letter headings.
                    </p>
                <?php endif; ?>
            </div>
        </aside>
    </div>
</div>
<?php $body = ob_get_clean(); ?>
<?php include __DIR__ . '/layout.php'; ?>
