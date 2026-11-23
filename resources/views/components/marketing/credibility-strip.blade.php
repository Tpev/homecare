@props(['compact' => false])

@once
    @push('head')
        <style>
            .marketing-credibility { display: grid; grid-template-columns: minmax(0, 1fr) auto; align-items: center; gap: 36px; padding-block: 28px; border-top: 1px solid var(--line, #ded6c6); color: var(--green, #23483f); }
            .marketing-credibility .credibility-label { margin: 0 0 18px; font-size: 10px; line-height: 1.5; font-weight: 600; letter-spacing: .14em; text-transform: uppercase; color: var(--muted, #57685e); }
            .marketing-credibility .credibility-logos { display: grid; grid-template-columns: 1.2fr .55fr 1fr 1.65fr; align-items: center; gap: 28px; margin: 0; padding: 0; list-style: none; }
            .marketing-credibility .credibility-logos li { display: flex; justify-content: center; align-items: center; min-width: 0; height: 38px; }
            .marketing-credibility .credibility-logos img { display: block; width: 100%; max-width: 180px; height: auto; max-height: 34px; object-fit: contain; filter: grayscale(1); opacity: .72; mix-blend-mode: multiply; }
            .marketing-credibility .credibility-logos .credibility-ap { width: 27px; }
            .marketing-credibility .credibility-partner { border-left: 1px solid var(--line, #ded6c6); padding-left: 36px; }
            .marketing-credibility .credibility-partner a { display: inline-flex; align-items: center; min-height: 44px; border-radius: 3px; }
            .marketing-credibility .credibility-partner img { display: block; width: 205px; height: auto; }
            .marketing-credibility a:focus-visible { outline: 3px solid var(--coral, #ad503d); outline-offset: 5px; }
            .marketing-credibility--compact { grid-template-columns: 1fr; gap: 20px; max-width: 530px; margin-top: 30px; padding-block: 22px 0; }
            .marketing-credibility--compact .credibility-logos { gap: 22px; }
            .marketing-credibility--compact .credibility-partner { display: flex; flex-wrap: wrap; gap: 12px 22px; align-items: center; padding: 18px 0 0; border-left: 0; border-top: 1px solid var(--line, #ded6c6); }
            .marketing-credibility--compact .credibility-partner .credibility-label { margin: 0; }
            .marketing-credibility--compact .credibility-partner img { width: 190px; }
            @media (max-width: 800px) {
                .marketing-credibility { grid-template-columns: 1fr; gap: 22px; }
                .marketing-credibility .credibility-partner { display: flex; flex-wrap: wrap; gap: 12px 24px; align-items: center; border-left: 0; border-top: 1px solid var(--line, #ded6c6); padding: 20px 0 0; }
                .marketing-credibility .credibility-partner .credibility-label { margin: 0; }
            }
            @media (max-width: 480px) {
                .marketing-credibility .credibility-logos { grid-template-columns: 1fr 1fr; gap: 18px 28px; }
                .marketing-credibility .credibility-logos img { max-width: 148px; }
                .marketing-credibility .credibility-logos .credibility-yahoo { max-width: 120px; }
                .marketing-credibility .credibility-logos .credibility-markets { max-width: 100px; }
                .marketing-credibility .credibility-partner img { width: 180px; }
            }
        </style>
    @endpush
@endonce

<aside {{ $attributes->class(['marketing-credibility', 'marketing-credibility--compact' => $compact]) }} aria-label="Press appearances and community partnership">
    <div class="credibility-press">
        <p class="credibility-label">As seen in</p>
        <ul class="credibility-logos" role="list" aria-label="Press appearances">
            <li><img class="credibility-yahoo" src="{{ asset('images/marketing/press/yahoo-news.svg') }}" alt="Yahoo News" width="124" height="20" decoding="async"></li>
            <li><img class="credibility-ap" src="{{ asset('images/marketing/press/associated-press.svg') }}" alt="Associated Press" width="158" height="195" decoding="async"></li>
            <li><img class="credibility-markets" src="{{ asset('images/marketing/press/markets-insider.svg') }}" alt="Markets Insider" width="93" height="30" decoding="async"></li>
            <li><img src="{{ asset('images/marketing/press/north-carolina-daily.png') }}" alt="North Carolina Daily" width="871" height="146" decoding="async"></li>
        </ul>
    </div>
    <div class="credibility-partner">
        <p class="credibility-label">Proud partner of</p>
        <a href="https://www.alz.org/" target="_blank" rel="noopener noreferrer" aria-label="Visit the Alzheimer's Association website (opens in a new tab)">
            <img src="{{ asset('images/marketing/partners/alzheimers-association.svg') }}" alt="Alzheimer’s Association" width="714" height="135" decoding="async">
        </a>
    </div>
</aside>
