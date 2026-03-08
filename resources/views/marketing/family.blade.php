@extends('layouts.marketing')

@section('title', 'For Families | HomeCare Raleigh')
@section('meta_description', 'Get trusted non-medical home care in Raleigh without the callback delay. Post a request, compare caregivers, chat, and hire fast.')
@section('canonical', route('landing.family'))

@section('content')
    <livewire:family-landing />
@endsection
