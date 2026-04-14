@extends('layouts.marketing')

@section('title', 'Home Care HUB | Trusted Home Support for Older Adults')
@section('meta_description', 'Home Care HUB helps families arrange trusted, non-medical support at home for an older parent, from 30-minute quick help to a few hours of support to traditional full-day coverage.')
@section('canonical', route('landing'))

@section('content')
    <livewire:family-landing />
@endsection
