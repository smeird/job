<?php
/** @var string $title */
/** @var string $body */

use App\Security\CspConfig;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title><?= htmlspecialchars($title ?? 'job.smeird.com', ENT_QUOTES) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tailwindcss@3.4.4/dist/tailwind.min.css">
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.13.5/dist/cdn.min.js" defer></script>
    <style>[x-cloak]{display:none!important;}</style>
</head>
<?php $fullWidth = $fullWidth ?? false; ?>
<body class="min-h-screen bg-slate-950 text-slate-100">
<?php if ($fullWidth) : ?>
    <div class="min-h-screen flex flex-col">
        <header class="border-b border-slate-800/60 bg-slate-900/80 backdrop-blur">
            <div class="mx-auto flex w-full max-w-6xl items-center justify-between px-6 py-5">
                <div>
                    <h1 class="text-2xl font-semibold">job.smeird.com</h1>
                    <?php if (!empty($subtitle)) : ?>
                        <p class="text-sm text-slate-400 mt-1"><?= htmlspecialchars($subtitle, ENT_QUOTES) ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </header>
        <main class="flex-1">
            <div class="mx-auto w-full max-w-6xl px-6 py-10">
                <?= $body ?>
            </div>
        </main>
    </div>
<?php else : ?>
    <div class="min-h-screen flex flex-col items-center justify-center p-6">
        <div class="w-full max-w-md bg-slate-900/70 shadow-xl rounded-lg p-8 space-y-6 border border-slate-800/70">
            <div class="text-center space-y-2">
                <h1 class="text-3xl font-bold tracking-tight">job.smeird.com</h1>
                <?php if (!empty($subtitle)) : ?>
                    <p class="text-slate-300"><?= htmlspecialchars($subtitle, ENT_QUOTES) ?></p>
                <?php endif; ?>
            </div>
            <?= $body ?>

        </div>
    </div>

<?php endif; ?>

</body>
</html>
