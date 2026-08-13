<div class="mx-auto max-w-7xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div><a href="{{ route('admin.ai-support.knowledge.index') }}" wire:navigate class="text-sm font-semibold text-emerald-700 hover:underline">&larr; Knowledge base</a><p class="mt-3 text-xs font-bold uppercase tracking-[0.15em] text-emerald-700">{{ $entry?->stable_id ?: 'New stable entry' }}</p><h1 class="mt-1 text-3xl font-extrabold tracking-tight text-slate-950">{{ $entry ? 'Knowledge editor' : 'Create knowledge draft' }}</h1></div>
        @if($version)<div class="flex items-center gap-2"><x-badge :text="'VERSION '.$version->version_number" color="slate" /><x-badge :text="strtoupper($version->status)" :color="$version->status === 'published' ? 'green' : ($version->status === 'paused' ? 'yellow' : 'blue')" /></div>@endif
    </div>
    @if(session('status'))<x-alert color="green">{{ session('status') }}</x-alert>@endif
    @foreach(['validation','version','lifecycleReason','confirmationText','delete'] as $field)@error($field)<x-alert color="red">{{ $message }}</x-alert>@enderror @endforeach

    @php $editable = ! $version || in_array($version->status, ['draft','in_review','approved'], true); @endphp
    <div class="grid gap-6 xl:grid-cols-[minmax(0,2fr)_minmax(300px,1fr)]">
        <form wire:submit="save" class="space-y-6">
            <x-card>
                <x-slot:header><h2 class="text-lg font-semibold">Answer and scope</h2></x-slot:header>
                <div class="space-y-4">
                    <label class="block text-sm font-semibold text-slate-800">Title<input wire:model="title" @disabled(! $editable) class="mt-1 min-h-11 w-full rounded-xl border-slate-300 text-base disabled:bg-slate-100" maxlength="255"></label>@error('title')<p class="text-sm text-rose-700">{{ $message }}</p>@enderror
                    <label class="block text-sm font-semibold text-slate-800">Approved answer or procedure<textarea wire:model="answerBody" @disabled(! $editable) rows="12" class="mt-1 w-full rounded-xl border-slate-300 text-base leading-7 disabled:bg-slate-100" maxlength="50000"></textarea></label>@error('answerBody')<p class="text-sm text-rose-700">{{ $message }}</p>@enderror
                    <div class="grid gap-4 md:grid-cols-3">
                        <label class="text-sm font-semibold text-slate-800">Type<select wire:model="type" @disabled(! $editable) class="mt-1 min-h-11 w-full rounded-xl border-slate-300 text-base">@foreach(['product_fact','task_playbook','navigation','escalation'] as $value)<option value="{{ $value }}">{{ str($value)->replace('_',' ')->headline() }}</option>@endforeach</select></label>
                        <label class="text-sm font-semibold text-slate-800">Sensitivity<select wire:model="sensitivity" @disabled(! $editable) class="mt-1 min-h-11 w-full rounded-xl border-slate-300 text-base">@foreach(['public','authenticated','shared_care','owner_only','restricted'] as $value)<option value="{{ $value }}">{{ str($value)->replace('_',' ')->headline() }}</option>@endforeach</select></label>
                        <label class="text-sm font-semibold text-slate-800">Product area<input wire:model="productArea" @disabled(! $editable) class="mt-1 min-h-11 w-full rounded-xl border-slate-300 text-base"></label>
                    </div>
                    <fieldset><legend class="text-sm font-semibold text-slate-800">Roles</legend><div class="mt-2 flex gap-5">@foreach(['family','caregiver'] as $role)<label class="flex items-center gap-2 text-sm"><input wire:model="roles" value="{{ $role }}" type="checkbox" @disabled(! $editable) class="rounded border-slate-300">{{ str($role)->headline() }}</label>@endforeach</div></fieldset>@error('roles')<p class="text-sm text-rose-700">{{ $message }}</p>@enderror
                    <div class="grid gap-4 md:grid-cols-2"><label class="text-sm font-semibold text-slate-800">Review by<input wire:model="reviewBy" type="date" @disabled(! $editable) class="mt-1 min-h-11 w-full rounded-xl border-slate-300 text-base"></label><label class="text-sm font-semibold text-slate-800">Optional expiration<input wire:model="expiresOn" type="date" @disabled(! $editable) class="mt-1 min-h-11 w-full rounded-xl border-slate-300 text-base"></label></div>
                </div>
            </x-card>

            <x-card>
                <x-slot:header><h2 class="text-lg font-semibold">Applicability and boundaries</h2></x-slot:header>
                <div class="grid gap-4 md:grid-cols-2">
                    @foreach([['membershipStatesText','Membership/account states'],['capabilityIdsText','Capability IDs'],['factsMayStateText','Facts the assistant may state'],['factsMustNotInferText','Facts it must not infer'],['nextActionsText','Next approved actions'],['escalationConditionsText','Escalation conditions'],['retrievalMatchText','Retrieval examples: should match'],['retrievalNoMatchText','Retrieval examples: should not match'],['evaluationIdsText','Evaluation IDs']] as [$model,$label])
                        <label class="text-sm font-semibold text-slate-800">{{ $label }}<textarea wire:model="{{ $model }}" @disabled(! $editable) rows="4" class="mt-1 w-full rounded-xl border-slate-300 text-base disabled:bg-slate-100" placeholder="One item per line"></textarea></label>
                    @endforeach
                </div>
                <fieldset class="mt-4"><legend class="text-sm font-semibold text-slate-800">Semantic route targets</legend><div class="mt-2 grid gap-2 sm:grid-cols-2">@foreach($navigationTargets as $targetId)<label class="flex items-start gap-2 text-sm text-slate-700"><input wire:model="routeTargetIds" value="{{ $targetId }}" type="checkbox" @disabled(! $editable) class="mt-0.5 rounded border-slate-300"><span>{{ $targetId }}</span></label>@endforeach</div></fieldset>
            </x-card>

            <x-card>
                <x-slot:header><div class="flex items-center justify-between"><h2 class="text-lg font-semibold">Authoritative sources</h2>@if($editable)<button type="button" wire:click="addSource" class="min-h-11 text-sm font-semibold text-emerald-700 underline">Add source</button>@endif</div></x-slot:header>
                <div class="space-y-4">@foreach($sources as $index => $source)<div class="rounded-xl border border-slate-200 p-4" wire:key="kb-source-{{ $index }}"><div class="grid gap-3 md:grid-cols-2"><label class="text-sm font-semibold">Source registry ID<input wire:model="sources.{{ $index }}.source_id" @disabled(! $editable) placeholder="SRC-SUPPORT-CHAT-001" class="mt-1 min-h-11 w-full rounded-xl border-slate-300 text-base"></label><label class="text-sm font-semibold">Title<input wire:model="sources.{{ $index }}.title" @disabled(! $editable) class="mt-1 min-h-11 w-full rounded-xl border-slate-300 text-base"></label><label class="text-sm font-semibold">URL, optional<input wire:model="sources.{{ $index }}.url" @disabled(! $editable) class="mt-1 min-h-11 w-full rounded-xl border-slate-300 text-base"></label><label class="text-sm font-semibold">Section/anchor<input wire:model="sources.{{ $index }}.section_anchor" @disabled(! $editable) class="mt-1 min-h-11 w-full rounded-xl border-slate-300 text-base"></label></div><label class="mt-3 block text-sm font-semibold">Fact supported<textarea wire:model="sources.{{ $index }}.fact_supported" @disabled(! $editable) rows="2" class="mt-1 w-full rounded-xl border-slate-300 text-base"></textarea></label>@if($editable)<button type="button" wire:click="removeSource({{ $index }})" class="mt-2 min-h-11 text-sm font-semibold text-rose-700 underline">Remove source</button>@endif</div>@endforeach</div>
            </x-card>

            <x-card><label class="block text-sm font-semibold text-slate-800">Change note<textarea wire:model="changeNote" @disabled(! $editable) rows="2" maxlength="500" class="mt-1 w-full rounded-xl border-slate-300 text-base disabled:bg-slate-100"></textarea></label>@if($editable)<div class="mt-4"><x-button type="submit" color="green" class="min-h-11 justify-center">{{ $entry ? 'Save draft' : 'Create draft' }}</x-button></div>@endif</x-card>
        </form>

        <aside class="space-y-6">
            @if($version)
                <x-card>
                    <x-slot:header><h2 class="text-lg font-semibold">Validation & release</h2></x-slot:header>
                    @if($version->validation_results)
                        <div class="rounded-xl {{ data_get($version->validation_results,'passed') ? 'bg-emerald-50 text-emerald-950' : 'bg-rose-50 text-rose-950' }} p-3 text-sm"><p class="font-bold">{{ data_get($version->validation_results,'passed') ? 'Validation passed' : 'Blocking validation issues' }}</p>@foreach((array) data_get($version->validation_results,'errors',[]) as $field => $messages)<p class="mt-1">{{ $field }}: {{ implode(' ', $messages) }}</p>@endforeach</div>
                    @else<p class="text-sm text-slate-600">Validation has not been run for this edit version.</p>@endif
                    <div class="mt-4 grid gap-2">
                        @if($version->status === 'draft')<x-button wire:click="runValidation" color="slate" light class="min-h-11 justify-center">Run validation</x-button><x-button wire:click="submitForReview" color="blue" class="min-h-11 justify-center">Submit for review</x-button>@endif
                        @if($version->status === 'in_review')<x-button wire:click="approve" color="green" class="min-h-11 justify-center">Self-review and approve</x-button>@endif
                    </div>
                    <p class="mt-3 text-xs text-slate-500">LoLo's two operators may each author, self-review, approve, and publish alone under DEC-022.</p>
                </x-card>

                @if(in_array($version->status, ['approved','published','paused'], true))
                    <x-card>
                        <x-slot:header><h2 class="text-lg font-semibold">Lifecycle action</h2></x-slot:header>
                        <label class="block text-sm font-semibold">Reason<textarea wire:model="lifecycleReason" rows="2" maxlength="500" class="mt-1 w-full rounded-xl border-slate-300 text-base"></textarea></label>
                        <label class="mt-3 block text-sm font-semibold">Type the action word<input wire:model="confirmationText" autocomplete="off" class="mt-1 min-h-11 w-full rounded-xl border-slate-300 text-base" placeholder="{{ $version->status === 'approved' ? 'PUBLISH' : ($version->status === 'paused' ? 'RESUME or WITHDRAW' : 'PAUSE or WITHDRAW') }}"></label>
                        <div class="mt-4 grid gap-2">@if($version->status === 'approved')<x-button wire:click="publish" color="green" class="min-h-11 justify-center">Publish now</x-button>@elseif($version->status === 'published')<x-button wire:click="pause" color="yellow" class="min-h-11 justify-center">Pause retrieval</x-button><x-button wire:click="withdraw" color="red" light class="min-h-11 justify-center">Withdraw</x-button>@elseif($version->status === 'paused')<x-button wire:click="resume" color="green" class="min-h-11 justify-center">Resume published version</x-button><x-button wire:click="withdraw" color="red" light class="min-h-11 justify-center">Withdraw</x-button>@endif</div>
                    </x-card>
                @endif

                @if(in_array($version->status, ['published','paused','superseded','withdrawn'], true))
                    <x-card><x-slot:header><h2 class="text-lg font-semibold">Create new version</h2></x-slot:header><p class="text-sm text-slate-600">Released content is immutable. Create a draft copy for changes.</p><textarea wire:model="newVersionChangeNote" rows="2" maxlength="500" placeholder="What will change?" class="mt-3 w-full rounded-xl border-slate-300 text-base"></textarea><x-button wire:click="createNewVersion" color="blue" class="mt-3 min-h-11 w-full justify-center">Create draft version</x-button></x-card>
                @endif

                <x-card><x-slot:header><h2 class="text-lg font-semibold">Version history</h2></x-slot:header><div class="space-y-2">@foreach($entry->versions as $historical)<button type="button" disabled class="w-full rounded-lg border border-slate-200 p-3 text-left text-sm"><span class="font-semibold">Version {{ $historical->version_number }} · {{ str($historical->status)->replace('_',' ')->headline() }}</span><span class="mt-1 block text-slate-500">{{ $historical->change_note }}</span></button>@endforeach</div></x-card>

                <x-card><x-slot:header><h2 class="text-lg font-semibold text-rose-900">Danger zone</h2></x-slot:header><p class="text-sm text-slate-600">An unreleased dependency-free draft is permanently deleted. Released truth is withdrawn and retained under the 36-month full-version plus 24-month tombstone rule.</p><textarea wire:model="lifecycleReason" rows="2" maxlength="500" placeholder="Deletion reason" class="mt-3 w-full rounded-xl border-rose-300 text-base"></textarea><label class="mt-3 block text-sm font-semibold">Type {{ $entry->stable_id }}<input wire:model="confirmationText" class="mt-1 min-h-11 w-full rounded-xl border-rose-300 text-base"></label><x-button wire:click="deleteEntry" color="red" class="mt-3 min-h-11 w-full justify-center">Delete entry/version</x-button></x-card>
            @else
                <x-card><p class="text-sm text-slate-600">Create the draft first. Then run validation and move it through review, approval, and publish-now.</p></x-card>
            @endif
        </aside>
    </div>
</div>
