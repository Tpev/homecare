@extends('layouts.marketing')

@section('title', 'For Caregivers | Flexible Home Care Work | LoLo')
@section('meta_description', 'Join LoLo as a caregiver for companionship and everyday non-medical home support. Create your profile and start receiving care opportunities that fit your schedule.')
@section('canonical', route('landing.caregiver'))
@section('og_image', asset('images/marketing/caregiver-hero-raleigh.jpg'))
@section('og_image_alt', 'A LoLo caregiver spending time with an older adult at home.')

@section('content')
    <livewire:caregiver-landing />
@endsection
