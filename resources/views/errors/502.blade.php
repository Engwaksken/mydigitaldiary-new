@extends('errors.layout')

@section('title', 'Service temporarily unavailable')
@section('icon', 'rotate-right')
@section('eyebrow', 'Connected service problem')
@section('heading', 'A connected service did not respond correctly')
@section('message', 'My Digital Diary is working, but a service needed to complete this request returned an unexpected response.')
@section('help', 'Please try again shortly. Payment, email or integration requests should not be repeatedly submitted while a previous request is still being processed.')
