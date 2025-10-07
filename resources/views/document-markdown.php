<?php
/** @var array{filename: string, created_at: string, size: string, mime_type: string, type_label: string, download_url: string|null, plain_view_url: string} $document */
/** @var array{raw: string, html: string} $markdown */
/** @var string $title */
/** @var string $subtitle */
/** @var array<int, array{href: string, label: string, current: bool}> $navLinks */
?>
<?php ob_start(); ?>
<?php
$viewer = [
    'eyebrow' => 'Document preview',
    'heading' => 'Formatted markdown',
    'description' => 'Headings, lists, and emphasis are rendered exactly as they will appear in generated content.',
    'backLink' => [
        'href' => $document['plain_view_url'],
        'label' => '← Back to plain view',
    ],
    'metadataTitle' => 'File details',
    'metadataDescription' => 'Key attributes for the stored document help verify you selected the right file.',
    'metadata' => [
        ['label' => 'Type', 'value' => $document['type_label']],
        ['label' => 'Uploaded', 'value' => $document['created_at']],
        ['label' => 'Size', 'value' => $document['size']],
        ['label' => 'MIME type', 'value' => $document['mime_type']],
    ],
    'viewerActions' => array_values(array_filter([
        !empty($document['download_url'])
            ? [
                'href' => $document['download_url'],
                'label' => 'Download original',
                'style' => 'emerald',
            ]
            : null,
    ])),
    'html' => $markdown['html'],
    'raw' => $markdown['raw'],
    'formattedLabel' => 'Formatted',
    'rawLabel' => 'Raw source',
    'formattedDescription' => 'Clean CommonMark rendering with unsafe HTML stripped for safety.',
    'rawDescription' => 'Copy and reuse the original text version if you need to edit locally.',
    'formattedAnchor' => 'formatted-markdown',
];
?>
<?php include __DIR__ . '/partials/markdown-viewer.php'; ?>
<?php $body = ob_get_clean(); ?>
<?php include __DIR__ . '/layout.php'; ?>
