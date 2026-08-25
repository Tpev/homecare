<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'LoLo')</title>
    <x-site-icons />
    <meta name="description" content="@yield('meta_description', 'LoLo helps families arrange trusted, non-medical home care and companionship for an older adult at home.')">
    <meta name="robots" content="@yield('robots', 'index,follow')">
    <link rel="canonical" href="@yield('canonical', request()->url())">

    <meta property="og:type" content="website">
    <meta property="og:site_name" content="LoLo">
    <meta property="og:title" content="@yield('title', 'LoLo')">
    <meta property="og:description" content="@yield('meta_description', 'LoLo helps families arrange trusted, non-medical home care and companionship for an older adult at home.')">
    <meta property="og:url" content="@yield('canonical', request()->url())">
    <meta property="og:image" content="@yield('og_image', asset('images/marketing/flyer.png'))">
    <meta property="og:image:alt" content="@yield('og_image_alt', 'LoLo helps families find trusted support for an older parent at home.')">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="@yield('title', 'LoLo')">
    <meta name="twitter:description" content="@yield('meta_description', 'LoLo helps families arrange trusted, non-medical home care and companionship for an older adult at home.')">
    <meta name="twitter:image" content="@yield('og_image', asset('images/marketing/flyer.png'))">
    <meta name="twitter:image:alt" content="@yield('og_image_alt', 'LoLo helps families find trusted support for an older parent at home.')">

    @stack('head')

    <x-analytics.google-tag />

    <script>
        !function(f,b,e,v,n,t,s)
        {if(f.fbq)return;n=f.fbq=function(){n.callMethod?
        n.callMethod.apply(n,arguments):n.queue.push(arguments)};
        if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
        n.queue=[];t=b.createElement(e);t.async=!0;
        t.src=v;s=b.getElementsByTagName(e)[0];
        s.parentNode.insertBefore(t,s)}(window, document,'script',
        'https://connect.facebook.net/en_US/fbevents.js');
        fbq('init', '1842558893085096');
        fbq('track', 'PageView');
    </script>
</head>

<body style="background:#FFF7EA;">
    <noscript>
        <img height="1" width="1" style="display:none"
             src="https://www.facebook.com/tr?id=1842558893085096&ev=PageView&noscript=1"
             alt="">
    </noscript>

    @yield('structured_data')
    @yield('content')
</body>
</html>
