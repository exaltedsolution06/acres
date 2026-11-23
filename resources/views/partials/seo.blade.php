<meta name="description" content="{{ $seo['description'] }}">
<link rel="canonical" href="{{ $seo['canonical'] }}">
@if(!empty($seo['robots']))<meta name="robots" content="{{ $seo['robots'] }}">@endif
<meta property="og:type" content="website">
<meta property="og:site_name" content="{{ config('app.name') }}">
<meta property="og:title" content="{{ $seo['title'] }}">
<meta property="og:description" content="{{ $seo['description'] }}">
<meta property="og:url" content="{{ $seo['canonical'] }}">
@if(!empty($seo['og_image']))<meta property="og:image" content="{{ $seo['og_image'] }}">@endif
<meta name="twitter:card" content="{{ !empty($seo['og_image']) ? 'summary_large_image' : 'summary' }}">
@if(!empty($jsonLd))<script type="application/ld+json">{!! \App\Support\Seo::json($jsonLd) !!}</script>@endif
@if(!empty($crumbs))<script type="application/ld+json">{!! \App\Support\Seo::json(\App\Support\Seo::breadcrumbs($crumbs)) !!}</script>@endif
