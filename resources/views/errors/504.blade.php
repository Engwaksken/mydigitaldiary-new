@extends('errors.layout')

@section('title', 'Service took too long')
@section('icon', 'clock')
@section('eyebrow', 'Service timeout')
@section('heading', 'A connected service took too long to respond')
@section('message', 'The request could not finish within the expected time. This can happen when an external service is busy.')
@section('help', 'Wait a moment before retrying, especially for payments or automatic posting.')
