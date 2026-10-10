@extends('errors.layout')

@section('title', 'Sign in required')
@section('icon', 'lock')
@section('eyebrow', 'Authentication required')
@section('heading', 'Please sign in to continue')
@section('message', 'Your session may have ended, or this page requires a signed-in My Digital Diary account.')

@section('primary_action')
    @if (\Illuminate\Support\Facades\Route::has('login'))
        <a href="{{ route('login') }}" class="error-btn error-btn-primary">
            Sign In
        </a>
    @else
        <a href="{{ url('/') }}" class="error-btn error-btn-primary">
            Go Home
        </a>
    @endif
@endsection
