<?php
/** @var string $title */
/** @var string $subtitle */
/** @var array{purge_after_days: int, apply_to: array<int, string>} $policy */
/** @var array<int, string> $allowedResources */
/** @var array<string, string> $resourceLabels */
/** @var array<int, string> $errors */
/** @var string|null $status */
/** @var string|null $csrfToken */
?>
<?php ob_start(); ?>
<div class="space-y-6">
    <div class="space-y-2">
        <h2 class="text-xl font-semibold">Configure retention</h2>
        <p class="text-slate-300">Choose how long records are kept and which systems are subject to automated purge.</p>
    </div>

    <?php if (!empty($status)) : ?>
        <div class="rounded-md border border-emerald-500 bg-emerald-900/40 px-4 py-3 text-sm text-emerald-100">
            <?= htmlspecialchars($status, ENT_QUOTES) ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($errors)) : ?>
        <div class="space-y-2">
            <?php foreach ($errors as $error) : ?>
                <div class="rounded-md border border-rose-500 bg-rose-900/40 px-4 py-3 text-sm text-rose-100">
                    <?= htmlspecialchars($error, ENT_QUOTES) ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form method="post" action="/retention" class="space-y-5">
        <input type="hidden" name="_token" value="<?= htmlspecialchars((string) $csrfToken, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
        <div class="space-y-2">
            <label for="purge_after_days" class="block text-sm font-medium">Purge records after (days)</label>
            <input
                type="number"
                min="1"
                id="purge_after_days"
                name="purge_after_days"
                value="<?= htmlspecialchars((string) ($policy['purge_after_days'] ?? 30), ENT_QUOTES) ?>"
                class="w-full rounded-md border border-slate-600 bg-slate-900 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-400"
                required
            >
            <p class="text-xs text-slate-400">Records older than this number of days will be permanently removed.</p>
        </div>

        <fieldset class="space-y-3">
            <legend class="text-sm font-medium">Apply to</legend>
            <p class="text-xs text-slate-400">Only the selected areas will be purged when the retention job runs.</p>
            <div class="space-y-2">
                <?php foreach ($allowedResources as $resource) : ?>
                    <?php $label = $resourceLabels[$resource] ?? ucfirst(str_replace('_', ' ', $resource)); ?>
                    <label class="flex items-center gap-3 text-sm">
                        <input
                            type="checkbox"
                            name="apply_to[]"
                            value="<?= htmlspecialchars($resource, ENT_QUOTES) ?>"
                            class="h-4 w-4 rounded border-slate-600 bg-slate-800 text-indigo-500 focus:ring-indigo-400"
                            <?= in_array($resource, $policy['apply_to'] ?? [], true) ? 'checked' : '' ?>
                        >
                        <span><?= htmlspecialchars($label, ENT_QUOTES) ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
        </fieldset>

        <div class="pt-2">
            <button type="submit" class="w-full rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-400">
                Save policy
            </button>
        </div>
    </form>
</div>
<?php $body = ob_get_clean(); ?>
<?php include __DIR__ . '/layout.php'; ?>
