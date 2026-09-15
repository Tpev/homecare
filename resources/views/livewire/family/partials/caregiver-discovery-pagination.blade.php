@if ($caregiverDiscoveryCount > 0)
    <div
        wire:key="caregiver-discovery-page-{{ $caregiverDiscoveryContext }}-{{ $caregiverDiscoveryLimit }}"
        data-testid="caregiver-discovery-pagination"
        class="flex flex-col items-center gap-3 border-t border-[#E4DDD3] p-6 text-center"
    >
        <p class="hc-recruit-caption" role="status" aria-live="polite">Showing {{ $caregiverDiscoveryCount }} {{ \Illuminate\Support\Str::plural('caregiver', $caregiverDiscoveryCount) }}</p>
        @if ($caregiverDiscoveryHasMore)
            <div
                x-data="{
                    loading: false,
                    async more() {
                        if (this.loading) return;
                        this.loading = true;
                        try { await this.$wire.loadMoreCaregivers({{ $caregiverDiscoveryLimit }}); }
                        finally { this.loading = false; }
                    }
                }"
                x-intersect.once.margin.300px="more()"
            >
                <button type="button" x-on:click="more()" x-bind:disabled="loading" class="hc-secondary-button min-h-11" data-testid="load-more-caregivers">
                    <span x-text="loading ? 'Loading caregivers…' : 'Show more caregivers'">Show more caregivers</span>
                </button>
            </div>
        @elseif ($caregiverDiscoveryCount >= \App\Services\Marketplace\CaregiverInvitationDiscoveryService::MAX_DISCOVERY_LIMIT)
            <p class="hc-recruit-caption">Search by name, city or certification to narrow these results.</p>
        @else
            <p class="hc-recruit-caption">You’ve reached the end of these results.</p>
        @endif
    </div>
@endif
