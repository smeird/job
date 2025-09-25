<?php
/** @var string $title */
/** @var string $subtitle */
/** @var string $actionUrl */
/** @var string $buttonLabel */
/** @var string|null $error */
/** @var string|null $status */
/** @var string|null $email */
/** @var array $links */
?>
<?php ob_start(); ?>
<form method="post" action="<?= htmlspecialchars($actionUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="space-y-4">
    <input type="hidden" name="_token" value="<?= htmlspecialchars((string) $csrfToken, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
    <div>
        <label for="email" class="block text-sm font-medium text-slate-200">Email</label>
        <input id="email" name="email" type="email" maxlength="255" required value="<?= htmlspecialchars($email ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="mt-1 block w-full rounded-md bg-slate-900 border border-slate-700 px-3 py-2 text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500">
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
<?php if (!empty($links)) : ?>
    <div class="text-center text-sm text-slate-400 space-y-1">
        <?php foreach ($links as $link): ?>
            <p><a href="<?= htmlspecialchars($link['href'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="hover:text-indigo-300"><?= htmlspecialchars($link['label'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></a></p>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
<?php $body = ob_get_clean(); ?>
<?php include __DIR__ . '/../layout.php'; ?>
