@php
    $routeName = request()->route()?->getName();
    $isLogin = $routeName === 'login';
    $isFamilyRegistration = $routeName === 'register';
    $isCaregiverRegistration = $routeName === 'caregiver.register';
    $isPasswordFlow = in_array($routeName, ['password.request', 'password.reset', 'password.confirm'], true);
    $isWideForm = $isFamilyRegistration || $isCaregiverRegistration;

    $pageTitle = match ($routeName) {
        'login' => 'Sign in',
        'register' => 'Create a family account',
        'caregiver.register' => 'Create a caregiver account',
        'password.request' => 'Reset your password',
        'password.reset' => 'Choose a new password',
        'password.confirm' => 'Confirm your password',
        'verification.notice' => 'Verify your email',
        default => 'Your account',
    };

    $story = match (true) {
        $isFamilyRegistration => [
            'kicker' => 'For families',
            'title' => 'Care coordination that feels lighter.',
            'description' => 'Find flexible, non-medical support and keep the details in one calm, shared place.',
            'points' => [
                'Tell caregivers what kind of help your family needs.',
                'Review profiles, availability, experience, and pricing.',
                'Keep schedules, messages, and visit updates together.',
            ],
        ],
        $isCaregiverRegistration => [
            'kicker' => 'For independent caregivers',
            'title' => 'Build trusted relationships. Work on your terms.',
            'description' => 'Create a profile that helps families understand your experience, availability, and approach to care.',
            'points' => [
                'Choose which opportunities fit your schedule.',
                'Set your service area, availability, and profile details.',
                'Manage visits, messages, and earnings in one workspace.',
            ],
        ],
        $isPasswordFlow => [
            'kicker' => 'Secure account recovery',
            'title' => 'Get back to the people and plans that matter.',
            'description' => 'We keep password recovery focused, private, and easy to complete.',
            'points' => [
                'Request a secure link using your account email.',
                'Choose a new password only you know.',
                'Return to your LoLo workspace and continue.',
            ],
        ],
        default => [
            'kicker' => 'Welcome to LoLo Care',
            'title' => 'Your care, schedule, and conversations—together.',
            'description' => 'Sign in to continue managing the details that keep care clear and connected.',
            'points' => [
                'See upcoming visits and care plans.',
                'Keep messages and family updates organized.',
                'Access care history and payment details securely.',
            ],
        ],
    };
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $pageTitle }} | LoLo Care</title>
    <meta name="description" content="LoLo helps families find trusted non-medical home care and companionship.">
    <link rel="canonical" href="{{ request()->url() }}">

    <meta property="og:type" content="website">
    <meta property="og:site_name" content="LoLo">
    <meta property="og:title" content="LoLo">
    <meta property="og:description" content="LoLo helps families find trusted non-medical home care and companionship.">
    <meta property="og:url" content="{{ request()->url() }}">
    <meta property="og:image" content="{{ asset('images/marketing/flyer.png') }}">
    <meta property="og:image:alt" content="LoLo helps families find trusted support for mom or dad at home.">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="LoLo">
    <meta name="twitter:description" content="LoLo helps families find trusted non-medical home care and companionship.">
    <meta name="twitter:image" content="{{ asset('images/marketing/flyer.png') }}">
    <meta name="twitter:image:alt" content="LoLo helps families find trusted support for mom or dad at home.">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Fraunces:opsz,wght@9..144,500;9..144,600&display=swap" rel="stylesheet">

    <x-analytics.google-tag />

    <!-- Meta Pixel Code -->
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
    <!-- End Meta Pixel Code -->

    <tallstackui:script />

    @livewireStyles
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        .auth-shell {
            min-height: 100svh;
            background: #fff9ef;
            color: #26332f;
            font-family: 'DM Sans', ui-sans-serif, system-ui, sans-serif;
        }

        .auth-story-panel {
            position: relative;
            isolation: isolate;
            min-height: 100svh;
            overflow: hidden;
            background:
                radial-gradient(circle at 10% 5%, rgba(255, 247, 234, .18), transparent 29%),
                radial-gradient(circle at 88% 80%, rgba(185, 87, 69, .42), transparent 34%),
                linear-gradient(145deg, #214b40 0%, #123c33 54%, #0c2f29 100%);
            box-shadow: 20px 0 70px rgba(18, 60, 51, .12);
        }

        .auth-story-panel::before,
        .auth-story-panel::after {
            position: absolute;
            z-index: -1;
            border: 1px solid rgba(255, 247, 234, .12);
            border-radius: 999px;
            content: '';
        }

        .auth-story-panel::before { width: 360px; height: 360px; right: -170px; top: -130px; }
        .auth-story-panel::after { width: 520px; height: 520px; left: -290px; bottom: -300px; }

        .auth-story-panel h2 {
            max-width: 620px;
            margin: 0;
            color: #fff7ea !important;
            font-family: 'Fraunces', ui-serif, Georgia, serif;
            font-size: clamp(2.7rem, 4vw, 4.85rem);
            font-weight: 500;
            letter-spacing: -.045em;
            line-height: 1.02;
        }

        .auth-story-kicker {
            margin: 0 0 18px;
            color: #f0ad9d;
            font-size: .75rem;
            font-weight: 700;
            letter-spacing: .19em;
            text-transform: uppercase;
        }

        .auth-story-description { max-width: 550px; color: rgba(255, 247, 234, .82); font-size: 1.06rem; line-height: 1.75; }

        .auth-benefit-list { display: grid; gap: 13px; margin-top: 34px; }
        .auth-benefit {
            display: flex;
            align-items: center;
            gap: 13px;
            max-width: 570px;
            border: 1px solid rgba(255, 247, 234, .13);
            border-radius: 16px;
            background: rgba(255, 255, 255, .07);
            color: rgba(255, 247, 234, .94);
            padding: 13px 15px;
            backdrop-filter: blur(8px);
        }

        .auth-benefit span {
            display: grid;
            flex: 0 0 auto;
            place-items: center;
            width: 25px;
            height: 25px;
            border-radius: 999px;
            background: #f0ad9d;
            color: #123c33;
            font-size: .77rem;
            font-weight: 800;
        }

        .auth-benefit p { margin: 0; font-size: .9rem; line-height: 1.45; }
        .auth-story-footer { color: rgba(255, 247, 234, .62); font-size: .76rem; letter-spacing: .03em; }

        .auth-main {
            position: relative;
            min-width: 0;
            min-height: 100svh;
            background:
                radial-gradient(circle at 88% 3%, rgba(79, 111, 175, .09), transparent 28%),
                radial-gradient(circle at 12% 94%, rgba(185, 87, 69, .09), transparent 30%),
                #fff9ef;
        }

        .auth-topbar { min-height: 76px; }
        .auth-topbar a { color: #855043; font-weight: 600; text-underline-offset: 4px; }
        .auth-topbar a:hover { color: #173f35; }

        .auth-mobile-intro {
            margin-bottom: 16px;
            border: 1px solid rgba(23, 63, 53, .1);
            border-radius: 18px;
            background: #edf3ed;
            color: #38564e;
            padding: 14px 16px;
            font-size: .87rem;
            line-height: 1.5;
        }

        .auth-mobile-intro strong { display: block; margin-bottom: 2px; color: #173f35; }

        .auth-card {
            border: 1px solid rgba(23, 63, 53, .11);
            border-radius: 30px;
            background: rgba(255, 255, 255, .95);
            padding: clamp(24px, 4vw, 44px);
            box-shadow: 0 24px 70px rgba(39, 52, 46, .11), 0 3px 12px rgba(39, 52, 46, .05);
        }

        .auth-card h1,
        .auth-card h2,
        .auth-card h3 {
            color: #173f35 !important;
            font-family: 'Fraunces', ui-serif, Georgia, serif;
        }

        .auth-card h1 { font-size: clamp(1.85rem, 3vw, 2.3rem); font-weight: 600; line-height: 1.12; }
        .auth-card label { color: #344c45; font-weight: 600; }
        .auth-card input:not([type='checkbox']):not([type='radio']),
        .auth-card select,
        .auth-card textarea {
            min-height: 49px;
            border-color: #d6cbbc !important;
            border-radius: 14px !important;
            background: transparent !important;
            color: #213b34 !important;
            box-shadow: 0 1px 2px rgba(23, 63, 53, .04) !important;
        }

        .auth-card .relative > .flex.rounded-md.ring-1 {
            border-radius: 14px !important;
            background: #fffcf7 !important;
            --tw-ring-color: #d6cbbc !important;
            box-shadow: 0 0 0 1px #d6cbbc !important;
        }

        .auth-card .relative > .flex.rounded-md.ring-1:focus-within {
            --tw-ring-color: #3c7163 !important;
            box-shadow: 0 0 0 2px #3c7163, 0 0 0 5px rgba(60, 113, 99, .13) !important;
        }

        .auth-card input:not([type='checkbox']):not([type='radio']):focus,
        .auth-card select:focus,
        .auth-card textarea:focus {
            border-color: #3c7163 !important;
            outline: none !important;
            box-shadow: none !important;
        }

        .auth-card input[type='checkbox'] {
            width: 18px;
            height: 18px;
            border-color: #bfb4a5;
            border-radius: 5px;
            color: #173f35;
        }

        .auth-card input[type='checkbox']:focus { --tw-ring-color: rgba(23, 63, 53, .25); }
        .auth-card a { color: #9d4f3e; font-weight: 600; text-decoration-color: rgba(157, 79, 62, .45); text-underline-offset: 3px; }
        .auth-card a:hover { color: #173f35; }

        .auth-primary {
            min-height: 49px !important;
            border: 1px solid #173f35 !important;
            border-radius: 14px !important;
            background: #173f35 !important;
            color: #fff7ea !important;
            padding-inline: 22px !important;
            font-weight: 700 !important;
            box-shadow: 0 10px 24px rgba(23, 63, 53, .18) !important;
        }

        .auth-primary:hover { background: #b95745 !important; border-color: #b95745 !important; transform: translateY(-1px); }
        .auth-primary:focus-visible { outline: 3px solid rgba(185, 87, 69, .3) !important; outline-offset: 3px; }

        .auth-role-switch {
            display: flex;
            align-items: center;
            gap: 13px;
            border: 1px solid #c9d9d2;
            border-radius: 16px;
            background: #edf3ed;
            color: #39564e;
            padding: 14px 16px;
            font-size: .9rem;
            line-height: 1.45;
        }

        .auth-role-switch > span:first-child {
            display: grid;
            flex: 0 0 auto;
            place-items: center;
            width: 34px;
            height: 34px;
            border-radius: 999px;
            background: #173f35;
            color: #fff7ea;
            font-size: 1rem;
        }

        .auth-consent {
            border: 1px solid #e4dacc;
            border-radius: 16px;
            background: #fffbf4;
            padding: 15px 16px;
        }

        .auth-legal { color: #6e766f; }
        .auth-legal a { color: #65716b; text-decoration-color: rgba(101, 113, 107, .35); text-underline-offset: 3px; }
        .auth-legal a:hover { color: #173f35; }

        @media (max-width: 1023px) {
            .auth-main { min-height: 100svh; }
            .auth-topbar { min-height: 68px; }
        }

        @media (max-width: 639px) {
            .auth-card { border-radius: 23px; padding: 22px 18px; }
            .auth-topbar { padding-inline: 18px; }
        }
    </style>
</head>

<body class="antialiased">
    <noscript>
        <img height="1" width="1" style="display:none"
             src="https://www.facebook.com/tr?id=1842558893085096&ev=PageView&noscript=1"
             alt="">
    </noscript>
    <x-toast />

    <div class="auth-shell lg:grid lg:grid-cols-[minmax(390px,0.86fr)_minmax(620px,1.14fr)]">
        <aside class="auth-story-panel hidden p-10 lg:flex lg:flex-col xl:p-14" aria-label="About LoLo Care">
            <a href="/" wire:navigate class="relative z-10 inline-flex w-fit items-center" aria-label="LoLo Care home">
                <img src="{{ asset('images/marketing/lolo/lolo-wordmark-ivory.svg') }}" alt="LoLo Care" class="h-11 w-auto">
            </a>

            <div class="relative z-10 my-auto py-12">
                <p class="auth-story-kicker">{{ $story['kicker'] }}</p>
                <h2>{{ $story['title'] }}</h2>
                <p class="auth-story-description mt-6">{{ $story['description'] }}</p>

                <div class="auth-benefit-list" aria-label="What you can do with LoLo Care">
                    @foreach ($story['points'] as $point)
                        <div class="auth-benefit">
                            <span aria-hidden="true">✓</span>
                            <p>{{ $point }}</p>
                        </div>
                    @endforeach
                </div>
            </div>

            <p class="auth-story-footer relative z-10 mb-0">LoLo Care · Flexible non-medical support at home</p>
        </aside>

        <main class="auth-main flex flex-col">
            <header class="auth-topbar flex items-center justify-between gap-4 px-5 py-4 sm:px-8 xl:px-12">
                <a href="/" wire:navigate class="inline-flex items-center lg:hidden" aria-label="LoLo Care home">
                    <img src="{{ asset('images/marketing/lolo/lolo-wordmark-evergreen.svg') }}" alt="LoLo Care" class="h-8 w-auto">
                </a>

                <a href="/" wire:navigate class="hidden text-sm lg:inline-flex">← Back to LoLo Care</a>

                <p class="m-0 text-right text-xs text-[#6B746F] sm:text-sm">
                    @if ($isLogin)
                        New to LoLo? <a href="{{ route('register') }}" wire:navigate>Create an account</a>
                    @elseif ($isFamilyRegistration || $isCaregiverRegistration)
                        Already registered? <a href="{{ route('login') }}" wire:navigate>Sign in</a>
                    @elseif ($isPasswordFlow)
                        Remembered it? <a href="{{ route('login') }}" wire:navigate>Sign in</a>
                    @else
                        <a href="/" wire:navigate>Back home</a>
                    @endif
                </p>
            </header>

            <div class="flex flex-1 items-center justify-center px-4 pb-8 pt-2 sm:px-8 sm:pb-10 xl:px-12">
                <div class="w-full {{ $isWideForm ? 'max-w-3xl' : 'max-w-xl' }}">
                    <div class="auth-mobile-intro lg:hidden">
                        <strong>{{ $story['kicker'] }}</strong>
                        {{ $story['description'] }}
                    </div>

                    <div class="auth-card">
                        {{ $slot }}
                    </div>

                    <footer class="auth-legal mt-5 px-2 text-center">
                        <div class="sm:hidden">
                            <details>
                                <summary class="cursor-pointer list-none text-xs font-semibold">Legal & privacy links</summary>
                                <div class="mt-3">
                                    <x-legal-links class="justify-center gap-x-3 gap-y-2 text-[11px]" />
                                </div>
                            </details>
                        </div>
                        <div class="hidden sm:block">
                            <x-legal-links class="justify-center gap-x-4 gap-y-2 text-[11px]" />
                        </div>
                    </footer>
                </div>
            </div>
        </main>
    </div>

    @livewireScripts
</body>
</html>
