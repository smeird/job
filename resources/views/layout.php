<?php
/** @var string $title */
/** @var string $body */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($title ?? 'job.smeird.com', ENT_QUOTES) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tailwindcss@3.4.4/dist/tailwind.min.css">
</head>
<body class="min-h-screen bg-slate-900 text-slate-100">
<div class="min-h-screen flex flex-col items-center justify-center p-6">
    <div class="w-full max-w-md bg-slate-800 shadow-xl rounded-lg p-8 space-y-6">
        <div class="text-center space-y-2">
            <h1 class="text-3xl font-bold tracking-tight">job.smeird.com</h1>
            <?php if (!empty($subtitle)) : ?>
                <p class="text-slate-300"><?= htmlspecialchars($subtitle, ENT_QUOTES) ?></p>
            <?php endif; ?>
        </div>
        <?= $body ?>
    </div>
</div>
</body>
</html>
