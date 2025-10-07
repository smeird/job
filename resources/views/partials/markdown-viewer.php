<?php
/**
 * @var array{
 *     eyebrow?: string|null,
 *     heading: string,
 *     description?: string|null,
 *     backLink?: array{href: string, label: string},
 *     heroActions?: array<int, array{href: string, label: string, style?: string, external?: bool}>,
 *     viewerActions?: array<int, array{href: string, label: string, style?: string, external?: bool}>,
 *     metadata?: array<int, array{label: string, value: string}>,
 *     metadataTitle?: string|null,
 *     metadataDescription?: string|null,
 *     html: string,
 *     raw?: string|null,
 *     formattedLabel?: string|null,
 *     rawLabel?: string|null,
 *     formattedDescription?: string|null,
 *     rawDescription?: string|null,
 *     defaultTab?: 'formatted'|'raw',
 *     formattedAnchor?: string|null,
 *     footerNote?: string|null
 * } $viewer
 */

$viewer = $viewer ?? [];
$eyebrow = isset($viewer['eyebrow']) ? trim((string) $viewer['eyebrow']) : null;
$heading = isset($viewer['heading']) ? trim((string) $viewer['heading']) : 'Markdown preview';
$description = isset($viewer['description']) ? trim((string) $viewer['description']) : null;
$backLink = isset($viewer['backLink']) && is_array($viewer['backLink']) ? $viewer['backLink'] : null;
$heroActions = isset($viewer['heroActions']) && is_array($viewer['heroActions']) ? $viewer['heroActions'] : [];
$viewerActions = isset($viewer['viewerActions']) && is_array($viewer['viewerActions']) ? $viewer['viewerActions'] : [];
$metadata = isset($viewer['metadata']) && is_array($viewer['metadata']) ? $viewer['metadata'] : [];
$metadataTitle = isset($viewer['metadataTitle']) ? trim((string) $viewer['metadataTitle']) : null;
$metadataDescription = isset($viewer['metadataDescription']) ? trim((string) $viewer['metadataDescription']) : null;
$html = $viewer['html'] ?? '';
$raw = array_key_exists('raw', $viewer) ? (string) $viewer['raw'] : null;
$formattedLabel = isset($viewer['formattedLabel']) ? trim((string) $viewer['formattedLabel']) : 'Formatted';
$rawLabel = isset($viewer['rawLabel']) ? trim((string) $viewer['rawLabel']) : 'Raw';
$formattedDescription = isset($viewer['formattedDescription']) ? trim((string) $viewer['formattedDescription']) : null;
$rawDescription = isset($viewer['rawDescription']) ? trim((string) $viewer['rawDescription']) : null;
$formattedAnchor = isset($viewer['formattedAnchor']) ? trim((string) $viewer['formattedAnchor']) : 'formatted-markdown';
$footerNote = isset($viewer['footerNote']) ? trim((string) $viewer['footerNote']) : null;
$defaultTab = isset($viewer['defaultTab']) && $viewer['defaultTab'] === 'raw' ? 'raw' : 'formatted';
$showRawTab = $raw !== null && $raw !== '';

if (!$showRawTab && $defaultTab === 'raw') {
    $defaultTab = 'formatted';
}

$actionStyles = [
    'primary' => 'inline-flex items-center gap-2 rounded-full border border-indigo-400/40 bg-indigo-500/20 px-4 py-2 text-xs '
        . 'font-semibold uppercase tracking-wide text-indigo-100 transition hover:border-indigo-300 hover:text-indigo-50',
    'emerald' => 'inline-flex items-center gap-2 rounded-full border border-emerald-400/40 bg-emerald-500/10 px-4 py-2 text-xs '
        . 'font-semibold uppercase tracking-wide text-emerald-100 transition hover:border-emerald-300 hover:text-emerald-50',
    'sky' => 'inline-flex items-center gap-2 rounded-full border border-sky-400/40 bg-sky-500/10 px-4 py-2 text-xs font-semibold '
        . 'uppercase tracking-wide text-sky-100 transition hover:border-sky-300 hover:text-sky-50',
    'rose' => 'inline-flex items-center gap-2 rounded-full border border-rose-400/40 bg-rose-500/10 px-4 py-2 text-xs font-semibold '
        . 'uppercase tracking-wide text-rose-100 transition hover:border-rose-300 hover:text-rose-50',
    'secondary' => 'inline-flex items-center gap-2 rounded-full border border-slate-700 px-4 py-2 text-xs font-semibold uppercase '
        . 'tracking-wide text-slate-200 transition hover:border-slate-500 hover:bg-slate-800/60 hover:text-slate-100',
];

?>
<div class="space-y-8">
    <header class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div class="space-y-2">
            <?php if ($eyebrow !== null && $eyebrow !== '') : ?>
                <p class="text-sm uppercase tracking-[0.3em] text-indigo-400"><?= htmlspecialchars($eyebrow, ENT_QUOTES) ?></p>
            <?php endif; ?>
            <h2 class="text-3xl font-semibold text-white"><?= htmlspecialchars($heading, ENT_QUOTES) ?></h2>
            <?php if ($description !== null && $description !== '') : ?>
                <p class="max-w-2xl text-sm text-slate-400"><?= htmlspecialchars($description, ENT_QUOTES) ?></p>
            <?php endif; ?>
        </div>
        <div class="flex flex-wrap gap-2 lg:self-end">
            <?php if ($backLink !== null) : ?>
                <a
                    href="<?= htmlspecialchars($backLink['href'], ENT_QUOTES) ?>"
                    class="inline-flex items-center gap-2 rounded-lg border border-slate-700 px-4 py-2 text-sm font-medium text-slate-200 transition hover:border-slate-500 hover:bg-slate-800/60"
                >
                    <?= htmlspecialchars($backLink['label'], ENT_QUOTES) ?>
                </a>
            <?php endif; ?>
            <?php foreach ($heroActions as $action) : ?>
                <?php
                $styleKey = isset($action['style']) ? (string) $action['style'] : 'secondary';
                $classes = $actionStyles[$styleKey] ?? $actionStyles['secondary'];
                $external = !empty($action['external']);
                ?>
                <a
                    href="<?= htmlspecialchars($action['href'], ENT_QUOTES) ?>"
                    <?php if ($external) : ?>target="_blank" rel="noopener"<?php endif; ?>
                    class="<?= $classes ?>"
                >
                    <?= htmlspecialchars($action['label'], ENT_QUOTES) ?>
                </a>
            <?php endforeach; ?>
        </div>
    </header>

    <section class="grid gap-6 xl:grid-cols-[minmax(0,340px),1fr]">
        <?php if (!empty($metadata) || $footerNote !== null) : ?>
            <aside class="space-y-6">
                <?php if (!empty($metadata)) : ?>
                    <article class="rounded-3xl border border-slate-800/80 bg-slate-900/70 p-6 shadow-xl">
                        <?php if ($metadataTitle !== null && $metadataTitle !== '') : ?>
                            <h3 class="text-lg font-semibold text-white"><?= htmlspecialchars($metadataTitle, ENT_QUOTES) ?></h3>
                        <?php endif; ?>
                        <?php if ($metadataDescription !== null && $metadataDescription !== '') : ?>
                            <p class="mt-2 text-sm text-slate-400"><?= htmlspecialchars($metadataDescription, ENT_QUOTES) ?></p>
                        <?php endif; ?>
                        <dl class="mt-4 space-y-3 text-sm text-slate-300">
                            <?php foreach ($metadata as $meta) : ?>
                                <div class="flex items-start justify-between gap-4">
                                    <dt class="text-slate-400"><?= htmlspecialchars($meta['label'], ENT_QUOTES) ?></dt>
                                    <dd class="text-right font-medium text-white"><?= htmlspecialchars($meta['value'], ENT_QUOTES) ?></dd>
                                </div>
                            <?php endforeach; ?>
                        </dl>
                    </article>
                <?php endif; ?>

                <?php if ($footerNote !== null && $footerNote !== '') : ?>
                    <div class="rounded-3xl border border-slate-800/70 bg-slate-950/60 p-4 text-xs text-slate-300">
                        <?= htmlspecialchars($footerNote, ENT_QUOTES) ?>
                    </div>
                <?php endif; ?>
            </aside>
        <?php endif; ?>

        <article
            id="<?= htmlspecialchars($formattedAnchor, ENT_QUOTES) ?>"
            class="space-y-6 rounded-3xl border border-slate-800/80 bg-slate-900/70 p-6 shadow-xl min-h-[60vh]"
            x-data="{ tab: '<?= $defaultTab ?>' }"
        >
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div class="flex items-center gap-2 rounded-full border border-slate-800/70 bg-slate-950/60 p-1 text-xs font-semibold uppercase tracking-wide text-slate-300">
                    <button
                        type="button"
                        class="rounded-full px-4 py-1 transition"
                        :class="tab === 'formatted' ? 'bg-indigo-500/20 text-indigo-100 shadow-inner' : 'text-slate-400 hover:text-slate-200'"
                        @click="tab = 'formatted'"
                    >
                        <?= htmlspecialchars($formattedLabel, ENT_QUOTES) ?>
                    </button>
                    <?php if ($showRawTab) : ?>
                        <button
                            type="button"
                            class="rounded-full px-4 py-1 transition"
                            :class="tab === 'raw' ? 'bg-indigo-500/20 text-indigo-100 shadow-inner' : 'text-slate-400 hover:text-slate-200'"
                            @click="tab = 'raw'"
                        >
                            <?= htmlspecialchars($rawLabel, ENT_QUOTES) ?>
                        </button>
                    <?php endif; ?>
                </div>
                <?php if (!empty($viewerActions)) : ?>
                    <div class="flex flex-wrap gap-2">
                        <?php foreach ($viewerActions as $action) : ?>
                            <?php
                            $styleKey = isset($action['style']) ? (string) $action['style'] : 'secondary';
                            $classes = $actionStyles[$styleKey] ?? $actionStyles['secondary'];
                            $external = !empty($action['external']);
                            ?>
                            <a
                                href="<?= htmlspecialchars($action['href'], ENT_QUOTES) ?>"
                                <?php if ($external) : ?>target="_blank" rel="noopener"<?php endif; ?>
                                class="<?= $classes ?>"
                            >
                                <?= htmlspecialchars($action['label'], ENT_QUOTES) ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <?php if (($formattedDescription !== null && $formattedDescription !== '') || ($showRawTab && $rawDescription !== null && $rawDescription !== '')) : ?>
                <div class="border-b border-slate-800/60 pb-4 text-xs text-slate-400">
                    <?php if ($formattedDescription !== null && $formattedDescription !== '') : ?>
                        <p x-show="tab === 'formatted'" x-cloak><?= htmlspecialchars($formattedDescription, ENT_QUOTES) ?></p>
                    <?php endif; ?>
                    <?php if ($showRawTab && $rawDescription !== null && $rawDescription !== '') : ?>
                        <p x-show="tab === 'raw'" x-cloak><?= htmlspecialchars($rawDescription, ENT_QUOTES) ?></p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <div class="space-y-6">
                <div
                    x-show="tab === 'formatted'"
                    x-cloak
                    class="prose prose-invert max-w-none space-y-4 text-slate-100 min-h-[50vh]"
                >
                    <?= $html ?>
                </div>
                <?php if ($showRawTab) : ?>
                    <pre
                        x-show="tab === 'raw'"
                        x-cloak
                        class="min-h-[50vh] overflow-auto rounded-2xl border border-slate-800/60 bg-slate-950/60 p-4 text-sm leading-relaxed text-slate-200 whitespace-pre-wrap break-words font-mono text-[13px]"
                    ><?= htmlspecialchars((string) $raw, ENT_QUOTES) ?></pre>
                <?php endif; ?>
            </div>
        </article>
    </section>
</div>
