@extends('layouts.marketing')

@section('title', 'Agency Partnerships | HomeCare Raleigh')
@section('meta_description', 'Explore HomeCare agency partnership workflows in Raleigh for demand capture, caregiver coordination, and quality tracking.')
@section('canonical', route('landing.agency'))

@section('content')
    <livewire:agency-landing />
@endsection
