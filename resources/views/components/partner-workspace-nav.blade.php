<nav class="pw-nav" aria-label="Partner workspace">
    <a href="{{ route('partner.dashboard') }}" wire:navigate @if(request()->routeIs('partner.dashboard')) aria-current="page" @endif>Overview</a>
    <a href="{{ route('partner.leads.index') }}" wire:navigate @if(request()->routeIs('partner.leads.*')) aria-current="page" @endif>My leads</a>
    <a href="{{ route('partner.billing.show') }}" wire:navigate @if(request()->routeIs('partner.billing.*')) aria-current="page" @endif>Plan & billing</a>
    <a href="{{ route('partner.profile.edit') }}" wire:navigate class="pw-nav-profile">Business profile <span aria-hidden="true">↗</span></a>
</nav>
