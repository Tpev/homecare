@extends('layouts.marketing')

@section('title', 'For Caregivers | Independent Caregiver Jobs in Raleigh')
@section('meta_description', 'Join HomeCare as an independent caregiver in Raleigh. Set your rates, choose your schedule, and build your reputation with real families.')
@section('canonical', route('landing.caregiver'))

@section('content')
    <livewire:caregiver-landing />
@endsection
