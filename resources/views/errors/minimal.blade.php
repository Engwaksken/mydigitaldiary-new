@extends('errors.layout')

@section('title', $exception?->getStatusCode() >= 500 ? 'Something went wrong' : 'Request could not be completed')
@section('icon', 'triangle-exclamation')
@section('eyebrow', 'Request could not be completed')
@section('heading', $exception?->getStatusCode() >= 500 ? 'Something did not work as expected' : 'We could not complete that request')
@section('message', 'Please return to a safe page and try again. Technical details are recorded securely for administrators.')
