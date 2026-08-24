@php
    $siteUrl = url('/');
    $pageTitle = 'PlayNova | پلتفرم مسابقات آنلاین Call of Duty Mobile';
    $pageDescription = 'PlayNova پلتفرم رسمی برگزاری مسابقات آنلاین Call of Duty Mobile با جوایز نقدی، ثبت‌نام آسان و رقابت زنده.';
    $logoUrl = url('/favicon-192x192.png');

    $structuredData = [
        '@context' => 'https://schema.org',
        '@graph' => [
            [
                '@type' => 'WebSite',
                '@id' => $siteUrl . '#website',
                'url' => $siteUrl,
                'name' => 'PlayNova',
                'description' => $pageDescription,
                'inLanguage' => 'fa-IR',
                'publisher' => ['@id' => $siteUrl . '#organization'],
            ],
            [
                '@type' => 'Organization',
                '@id' => $siteUrl . '#organization',
                'name' => 'PlayNova',
                'url' => $siteUrl,
                'logo' => [
                    '@type' => 'ImageObject',
                    'url' => $logoUrl,
                ],
            ],
            [
                '@type' => 'WebPage',
                '@id' => $siteUrl . '#webpage',
                'url' => $siteUrl,
                'name' => $pageTitle,
                'description' => $pageDescription,
                'isPartOf' => ['@id' => $siteUrl . '#website'],
                'about' => ['@id' => $siteUrl . '#organization'],
                'inLanguage' => 'fa-IR',
            ],
        ],
    ];
@endphp
<script type="application/ld+json">{!! json_encode($structuredData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>
