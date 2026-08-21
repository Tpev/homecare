<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-[#C85C48]">Secure Codex connection</p>
            <h1 class="mt-1 text-2xl font-bold text-[#17313F]">Authorize LoLo Care content access</h1>
        </div>
    </x-slot>

    <div class="mx-auto max-w-2xl px-4 py-10 sm:px-6">
        <div class="rounded-3xl border border-[#DED6CA] bg-white p-6 shadow-sm sm:p-8">
            <p class="text-base text-[#425B63]">
                <strong class="text-[#17313F]">{{ $client->name }}</strong> wants to use the LoLo Care Content MCP as
                <strong class="text-[#17313F]">{{ auth()->user()->name }}</strong>.
            </p>

            <div class="mt-6 rounded-2xl bg-[#F7F3ED] p-5">
                <h2 class="font-semibold text-[#17313F]">Requested permissions</h2>
                <ul class="mt-3 space-y-3">
                    @foreach ($scopeDescriptions as $scope => $description)
                        <li class="flex gap-3">
                            <span class="mt-1 text-[#2F766F]" aria-hidden="true">✓</span>
                            <span>
                                <strong class="block text-sm text-[#17313F]">{{ $scope }}</strong>
                                <span class="text-sm text-[#5E6E72]">{{ $description }}</span>
                            </span>
                        </li>
                    @endforeach
                </ul>
            </div>

            @if ($scopeDescriptions->has(\App\Models\ContentApiToken::ABILITY_SCHEDULE) || $scopeDescriptions->has(\App\Models\ContentApiToken::ABILITY_PUBLISH))
                <p class="mt-5 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                    Scheduling and publishing are high-impact writes. Keep Codex configured to prompt before those tools run; the CMS readiness gates remain enforced in every case.
                </p>
            @endif

            <p class="mt-5 text-sm text-[#5E6E72]">
                Every CMS action is attributed to your LoLo Care user. You can disconnect this server from Codex at any time; administrators can also revoke the hosted service credential.
            </p>

            <form method="POST" action="{{ route('content-mcp.oauth.decide') }}" class="mt-7 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                @csrf
                @foreach (['client_id', 'redirect_uri', 'response_type', 'scope', 'state', 'code_challenge', 'code_challenge_method', 'resource'] as $field)
                    <input type="hidden" name="{{ $field }}" value="{{ $parameters[$field] }}">
                @endforeach
                <button type="submit" name="decision" value="deny" class="rounded-full border border-[#C9C1B5] px-5 py-2.5 text-sm font-semibold text-[#425B63] hover:bg-[#F7F3ED]">
                    Deny
                </button>
                <button type="submit" name="decision" value="allow" class="rounded-full bg-[#0F5C5A] px-5 py-2.5 text-sm font-semibold text-white hover:bg-[#0B4B49]">
                    Allow Codex
                </button>
            </form>
        </div>
    </div>
</x-app-layout>
