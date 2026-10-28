<section class="rounded-2xl border border-emerald-200 bg-emerald-50/40 p-4">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h3 class="text-base font-bold text-slate-950">Text messages</h3>
            <p class="mt-1 text-xs text-slate-600">Send to {{ $lead->phone ?: 'No phone saved' }}</p>
        </div>
        <button type="button" wire:click="startMessage" @disabled($showComposer || $pendingMessageId || !$lead->phone || $lead->do_not_contact_at) class="min-h-10 rounded-xl bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800 disabled:cursor-not-allowed disabled:opacity-50">Send SMS</button>
    </div>
    @if($lead->do_not_contact_at)<p class="mt-2 text-sm text-rose-700">This lead is marked do not contact.</p>@endif
    @if($notice)<p role="status" @if($pendingMessageId) wire:poll.5s="refreshPendingMessage" @endif class="mt-3 text-sm text-emerald-800">{{ $notice }}</p>@endif

    @if($showComposer)
        <form wire:submit="sendMessage" class="mt-4 space-y-3">
            <p class="text-xs text-slate-600">From {{ $senderPhone ?: 'Sending number not configured' }} · To {{ $lead->phone }}</p>
            <fieldset>
                <legend class="text-sm font-semibold text-slate-700">Write your message</legend>
                <div class="mt-2 flex flex-wrap gap-3">
                    <label class="flex min-h-11 cursor-pointer items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-700">
                        <input type="radio" wire:model.live="messageMode" @disabled($pendingMessageId) name="sms-mode-{{ $this->getId() }}" value="free_text" class="border-slate-300 text-emerald-700 focus:ring-emerald-600">
                        Free text
                    </label>
                    <label class="flex min-h-11 cursor-pointer items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-700">
                        <input type="radio" wire:model.live="messageMode" @disabled($pendingMessageId) name="sms-mode-{{ $this->getId() }}" value="template" class="border-slate-300 text-emerald-700 focus:ring-emerald-600">
                        Use a template
                    </label>
                </div>
            </fieldset>
            @if($messageMode === 'template')
                <label class="block">
                    <span class="text-sm font-semibold text-slate-700">Message template</span>
                    <select wire:model.live="selectedTemplateId" @disabled($pendingMessageId) class="mt-1 min-h-11 w-full rounded-xl border-slate-200 text-sm">
                        <option value="">Choose a template</option>
                        @foreach($templates as $template)<option value="{{ $template->id }}">{{ $template->name }}</option>@endforeach
                    </select>
                </label>
                @error('selectedTemplateId')<p class="text-sm text-rose-700">{{ $message }}</p>@enderror
            @else
                <p class="text-xs text-slate-600">Type your message below. No template needed.</p>
            @endif
            <label class="block">
                <span class="text-sm font-semibold text-slate-700">Message</span>
                <textarea wire:model="smsBody" @disabled($pendingMessageId) rows="4" maxlength="1600" required aria-describedby="sms-help-{{ $this->getId() }}" class="mt-1 w-full rounded-xl border-slate-200 text-sm" placeholder="Type your message here…"></textarea>
            </label>
            <p id="sms-help-{{ $this->getId() }}" class="text-xs text-slate-500">Up to 1,600 characters.@if($messageMode === 'template') You can edit the template text before sending.@endif</p>
            @foreach(['smsBody', 'phone', 'send'] as $field)
                @error($field)<p role="alert" class="text-sm text-rose-700">{{ $message }}</p>@enderror
            @endforeach
            <div class="flex flex-wrap justify-end gap-2">
                <button type="button" wire:click="$set('showComposer', false)" wire:loading.attr="disabled" wire:target="sendMessage" class="min-h-10 rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700">Cancel</button>
                <button type="submit" @disabled($pendingMessageId) wire:loading.attr="disabled" wire:target="sendMessage" class="min-h-10 rounded-xl bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800 disabled:opacity-50">
                    <span wire:loading.remove wire:target="sendMessage">Send text</span><span wire:loading wire:target="sendMessage">Sending…</span>
                </button>
            </div>
        </form>
    @endif

    <button type="button" wire:click="$toggle('showTemplates')" aria-expanded="{{ $showTemplates ? 'true' : 'false' }}" class="mt-3 min-h-10 text-sm font-semibold text-emerald-800 underline underline-offset-4">{{ $showTemplates ? 'Close templates' : 'Manage SMS templates' }}</button>
    @if($showTemplates)
        <div class="mt-3 space-y-4 border-t border-emerald-200 pt-4">
            <div class="flex items-center justify-between gap-2"><h4 class="font-semibold text-slate-900">Shared SMS templates</h4><button type="button" wire:click="newTemplate" class="min-h-10 text-sm font-semibold text-emerald-800">New template</button></div>
            <div class="max-h-64 space-y-2 overflow-y-auto">
                @forelse($templates as $template)
                    <div wire:key="sms-template-{{ $template->id }}" class="rounded-xl border border-slate-200 bg-white p-3">
                        <p class="break-words text-sm font-semibold text-slate-900">{{ $template->name }}</p>
                        <p class="mt-1 whitespace-pre-wrap break-words text-xs text-slate-600">{{ $template->body }}</p>
                        <div class="mt-1 flex gap-4">
                            <button type="button" wire:click="editTemplate({{ $template->id }})" class="min-h-10 text-sm font-semibold text-emerald-800" aria-label="Edit template {{ $template->name }}">Edit</button>
                            <button type="button" wire:click="deleteTemplate({{ $template->id }})" wire:confirm="Delete this shared SMS template? Sent messages will remain in the timeline." class="min-h-10 text-sm font-semibold text-rose-700" aria-label="Delete template {{ $template->name }}">Delete</button>
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-slate-500">No templates yet. Create the first one below.</p>
                @endforelse
            </div>
            <form wire:submit="saveTemplate" class="space-y-3">
                <h4 class="text-sm font-semibold text-slate-900">{{ $editingTemplateId ? 'Edit template' : 'Create template' }}</h4>
                <label class="block"><span class="text-sm text-slate-700">Template name</span><input wire:model="templateName" maxlength="120" required class="mt-1 min-h-11 w-full rounded-xl border-slate-200 text-sm"></label>
                @error('templateName')<p class="text-sm text-rose-700">{{ $message }}</p>@enderror
                <label class="block"><span class="text-sm text-slate-700">Template text</span><textarea wire:model="templateBody" rows="4" maxlength="1600" required class="mt-1 w-full rounded-xl border-slate-200 text-sm"></textarea></label>
                @error('templateBody')<p class="text-sm text-rose-700">{{ $message }}</p>@enderror
                <button type="submit" wire:loading.attr="disabled" wire:target="saveTemplate" class="min-h-10 rounded-xl border border-emerald-700 px-4 py-2 text-sm font-semibold text-emerald-800">Save template</button>
            </form>
        </div>
    @endif
</section>
