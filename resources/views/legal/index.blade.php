@extends('layouts.marketing')

@section('title', 'Legal Center | HomeCare')
@section('meta_description', 'Legal terms, privacy, payment, and policy documents for HomeCare users, caregivers, and partners.')
@section('canonical', route('legal.index'))

@section('content')
    <div class="min-h-screen bg-slate-50 text-slate-900">
        <header class="sticky top-0 z-50 border-b border-slate-200/80 bg-white/90 backdrop-blur">
            <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-3 sm:px-6 lg:px-8">
                <a href="{{ route('landing.caregiver') }}" class="flex items-center gap-3">
                    <x-application-logo class="h-9 w-9" />
                    <div>
                        <div class="text-base font-bold tracking-tight">HomeCare</div>
                        <div class="text-xs text-slate-500">Legal Center</div>
                    </div>
                </a>
                <a href="{{ route('landing.caregiver') }}" class="text-sm font-semibold text-cyan-700 hover:underline">Back to pre-launch page</a>
            </div>
        </header>

        <main class="mx-auto max-w-6xl px-4 py-10 sm:px-6 lg:px-8">
            <h1 class="text-3xl font-black tracking-tight sm:text-4xl">Legal Documents</h1>
            <p class="mt-2 max-w-2xl text-sm text-slate-600">Review platform policies, terms, and acknowledgments currently published for HomeCare/HUB Healthcare.</p>

            <div class="mt-8 grid gap-4 md:grid-cols-2">
                @foreach ($pages as $item)
                    <a href="{{ $item['url'] }}" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
                        <h2 class="text-lg font-bold">{{ $item['title'] }}</h2>
                        <p class="mt-2 text-sm text-slate-600">{{ $item['description'] }}</p>
                        <p class="mt-3 text-sm font-semibold text-cyan-700">Read document</p>
                    </a>
                @endforeach
            </div>
        </main>
    </div>
@endsection

