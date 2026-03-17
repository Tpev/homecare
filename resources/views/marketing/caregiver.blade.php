@extends('layouts.marketing')

@section('title', 'For Caregivers | Raleigh & Wake County Pre-Launch | HomeCare')
@section('meta_description', 'Join HomeCare caregiver pre-launch in Raleigh and Wake County. Complete profile, verification, and setup now to get priority access to first family requests.')
@section('canonical', route('landing.caregiver'))

@section('content')
    <livewire:caregiver-landing />
@endsection
