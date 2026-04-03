@extends('layouts.marketing')

@section('title', 'HomeCare Raleigh | Trusted Home Care Support for Families')
@section('meta_description', 'Help your family arrange non-medical home care in Raleigh with more clarity and confidence. Review caregivers, message directly, and stay informed in one place.')
@section('canonical', route('landing'))

@section('content')
    <livewire:family-landing />
@endsection
