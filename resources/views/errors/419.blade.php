@extends('errors.layout')

@section('title', 'Session expired')
@section('icon', 'clock-rotate-left')
@section('eyebrow', 'Session expired')
@section('heading', 'Your page session has expired')
@section('message', 'For your security, this form can no longer be submitted from the current page.')
@section('help', 'Refresh the page, sign in again if necessary, and repeat the action. Your account data remains safe.')

@section('primary_action')
    <a href="{{ url()->current() }}" class="error-btn error-btn-primary">
        Refresh Page
    </a>
@endsection
