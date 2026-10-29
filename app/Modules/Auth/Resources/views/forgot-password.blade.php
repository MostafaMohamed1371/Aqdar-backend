@extends('auth::layouts.guest')

@section('title', __('structer.forgot_password_title'))

@section('content')
    <h1>{{ __('structer.forgot_password_title') }}</h1>
    <p class="sub">{{ __('structer.forgot_password_hint') }}</p>

    <form method="POST" action="{{ route('password.email') }}" novalidate>
        @csrf

        <x-form-input name="email" :label="__('structer.email')" type="email" :placeholder="__('structer.email_placeholder')" required autofocus />

        <button type="submit" class="btn">{{ __('structer.send_reset_link') }}</button>
    </form>

    <p class="links">
        <a href="{{ route('dashboard') }}">{{ __('structer.back_to_dashboard') }}</a>
    </p>
@endsection
