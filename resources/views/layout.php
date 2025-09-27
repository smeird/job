<?php
/** @var string $title */
/** @var string $body */

use App\Security\CspConfig;

$fullWidth = $fullWidth ?? false;
$navLinks = $navLinks ?? [];
$additionalHead = $additionalHead ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title><?= htmlspecialchars($title ?? 'job.smeird.com', ENT_QUOTES) ?></title>
    <meta name="application-name" content="job.smeird.com">
    <meta name="apple-mobile-web-app-title" content="job.smeird.com">
    <script src="/assets/js/tailwind-config.js"></script>
    <script src="https://cdn.tailwindcss.com?plugins=forms,typography"></script>
    <script><?= CspConfig::ALPINE_INIT_SCRIPT ?></script>
    <link rel="stylesheet" href="/assets/css/app.css">
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.13.5/dist/cdn.min.js" defer></script>
    <style>[x-cloak]{display:none!important;}</style>
    <?= $additionalHead ?>
</head>
<body id="site-job-smeird-com" data-site-id="job.smeird.com" class="min-h-screen bg-slate-950 text-slate-100">
<?php if ($fullWidth) : ?>
    <div class="min-h-screen flex flex-col">
        <header class="border-b border-slate-800/60 bg-slate-900/80 backdrop-blur">
            <div class="mx-auto w-full max-w-6xl px-6 py-5">
                <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                    <div>
                        <h1 class="text-2xl font-semibold">job.smeird.com</h1>
                        <?php if (!empty($subtitle)) : ?>
                            <p class="mt-1 text-sm text-slate-400"><?= htmlspecialchars($subtitle, ENT_QUOTES) ?></p>
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($navLinks)) : ?>
                        <nav class="flex flex-wrap gap-2 text-sm font-medium text-slate-300">
                            <?php foreach ($navLinks as $link) : ?>
                                <?php
                                $isCurrent = !empty($link['current']);
                                $classes = $isCurrent
                                    ? 'inline-flex items-center gap-2 rounded-full border border-indigo-400/40 bg-indigo-500/20 px-4 py-2 text-indigo-100'
                                    : 'inline-flex items-center gap-2 rounded-full border border-slate-700 px-4 py-2 text-slate-300 transition hover:border-slate-500 hover:bg-slate-800/60 hover:text-slate-100';
                                ?>
                                <a href="<?= htmlspecialchars($link['href'], ENT_QUOTES) ?>" class="<?= $classes ?>">
                                    <?= htmlspecialchars($link['label'], ENT_QUOTES) ?>
                                </a>
                            <?php endforeach; ?>
                        </nav>
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
        <?php if (!empty($navLinks)) : ?>
            <nav class="mb-6 flex flex-wrap justify-center gap-2 text-sm font-medium text-slate-300">
                <?php foreach ($navLinks as $link) : ?>
                    <?php
                    $isCurrent = !empty($link['current']);
                    $classes = $isCurrent
                        ? 'inline-flex items-center gap-2 rounded-full border border-indigo-400/40 bg-indigo-500/20 px-4 py-2 text-indigo-100'
                        : 'inline-flex items-center gap-2 rounded-full border border-slate-700 px-4 py-2 text-slate-300 transition hover:border-slate-500 hover:bg-slate-800/60 hover:text-slate-100';
                    ?>
                    <a href="<?= htmlspecialchars($link['href'], ENT_QUOTES) ?>" class="<?= $classes ?>">
                        <?= htmlspecialchars($link['label'], ENT_QUOTES) ?>
                    </a>
                <?php endforeach; ?>
            </nav>
        <?php endif; ?>
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
