<?php

namespace App\Support;

class Seo
{
    /** Safe for embedding inside <script type="application/ld+json">. */
    public static function json(array $data): string
    {
        return json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    }

    /** @param array<int, array{0: string, 1: ?string}> $crumbs */
    public static function breadcrumbs(array $crumbs): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => collect($crumbs)->values()->map(fn ($c, $i) => array_filter([
                '@type' => 'ListItem', 'position' => $i + 1, 'name' => $c[0], 'item' => $c[1],
            ]))->all(),
        ];
    }
}
