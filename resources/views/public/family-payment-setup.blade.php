<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow, noarchive">
    <meta name="referrer" content="no-referrer">
    <title>Secure payment setup · LoLo Care</title>
    <style>
        * { box-sizing: border-box; }
        body { margin:0; min-height:100svh; background:#f7f6f2; color:#203c33; font-family:ui-sans-serif,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif; }
        main { max-width:550px; margin:0 auto; padding:42px 20px; }
        .brand { display:block; width:107px; height:auto; margin:0 auto 36px; }
        .card { padding:36px; border:1px solid #e2e7df; border-radius:24px; background:white; box-shadow:0 12px 40px #203c3308; }
        .icon { display:grid; place-items:center; width:48px; height:48px; margin-bottom:24px; border-radius:16px; background:#edf4ee; color:#2e634f; font-size:25px; }
        .eyebrow { margin:0 0 12px; color:#698074; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.15em; }
        h1 { margin:0 0 15px; font-size:30px; font-weight:600; letter-spacing:-.035em; line-height:1.16; }
        p { color:#69776f; font-size:15px; line-height:1.7; }
        .notice { padding:15px 17px; border-radius:12px; background:#f3f6f1; color:#42634d; font-size:14px; }
        .error { color:#9c3b30; background:#fff1ed; }
        .steps { padding:0; margin:24px 0; list-style:none; display:grid; gap:14px; color:#5c6f62; font-size:14px; }
        .steps li { display:flex; gap:12px; align-items:center; }
        .steps span { width:23px; height:23px; display:grid; place-items:center; flex-shrink:0; border-radius:50%; background:#edf3ed; color:#3d654c; font-size:11px; font-weight:700; }
        label { display:flex; align-items:flex-start; gap:12px; margin-top:28px; padding-top:23px; border-top:1px solid #ecede8; color:#586c5e; font-size:13px; line-height:1.65; cursor:pointer; }
        input { width:18px; height:18px; flex-shrink:0; margin:3px 0 0; accent-color:#24513f; }
        a { color:#285b46; text-underline-offset:3px; }
        button { width:100%; margin-top:23px; min-height:50px; border:0; border-radius:12px; color:white; background:#254f3f; font-family:inherit; font-size:14px; font-weight:600; cursor:pointer; padding:14px; }
        button:hover { background:#183e2f; }
        button:focus-visible, a:focus-visible, input:focus-visible { outline:3px solid #8bb39b; outline-offset:4px; }
        footer { margin-top:24px; color:#819083; text-align:center; font-size:12px; line-height:1.7; }
        @media(max-width:500px) { main { padding:27px 16px; } .card { padding:26px; } h1 { font-size:27px; } }
    </style>
</head>
<body>
<main>
    <img class="brand" src="{{ asset('images/marketing/lolo/lolo-wordmark-evergreen.svg') }}" alt="LoLo Care">
    <section class="card" aria-labelledby="setup-title">
        @if ($state === 'completed')
            <div class="icon" aria-hidden="true">✓</div>
            <p class="eyebrow">Ready for the next step</p>
            <h1 id="setup-title">Payment method saved</h1>
            <p>You’re all set. Your payment method is securely saved to your family’s LoLo account.</p>
            <p class="notice">No payment was collected during this setup. You can close this page and continue with your LoLo contact.</p>
        @elseif ($state === 'pending')
            <div class="icon" aria-hidden="true">↻</div>
            <p class="eyebrow">One last check</p>
            <h1 id="setup-title">We’re confirming your payment method</h1>
            <p>This can take a moment. Your LoLo contact will see when setup is complete. You don’t need to enter your details again.</p>
            <form method="get"><button type="submit">Check confirmation</button></form>
        @elseif ($state === 'unavailable')
            <div class="icon" aria-hidden="true">↗</div>
            <p class="eyebrow">Payment setup</p>
            <h1 id="setup-title">This link is no longer available</h1>
            <p>Please ask your LoLo contact for a new payment setup link.</p>
        @else
            <div class="icon" aria-hidden="true">✓</div>
            <p class="eyebrow">A simple step before care begins</p>
            <h1 id="setup-title">Add your payment method</h1>
            <p>Securely save a payment method to your family’s LoLo account. No sign-in is needed.</p>
            <p class="notice">No payment is collected now.</p>
            @if ($cancelled)
                <p class="notice">You left payment setup. Continue below whenever you’re ready.</p>
            @endif
            @if ($error)
                <p class="notice error" role="alert">{{ $error }}</p>
            @endif
            <ol class="steps">
                <li><span>1</span>Continue to Stripe’s secure payment page.</li>
                <li><span>2</span>Add your details and complete any bank verification.</li>
                <li><span>3</span>Your payment method is ready for future care.</li>
            </ol>
            <form method="post" action="{{ $startUrl }}">
                @csrf
                <label>
                    <input type="checkbox" name="consent" value="1" required>
                    <span>I authorize LoLo Care to save this payment method and charge it for care I authorize, under the <a href="{{ route('legal.show', ['slug' => 'payment-terms-and-cancellation-policy']) }}" target="_blank" rel="noopener noreferrer">payment terms</a>.</span>
                </label>
                <button type="submit">Continue to secure payment setup</button>
            </form>
        @endif
    </section>
    <footer>Payment details are handled securely by Stripe.<br>LoLo Care does not store your full card number.</footer>
</main>
</body>
</html>
