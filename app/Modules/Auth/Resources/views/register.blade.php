@extends('auth::layouts.guest')

@section('title', __('structer.register'))

@section('content')
    <h1>{{ __('structer.create_account') }}</h1>
    <p class="sub">{{ __('structer.sign_up_to_dashboard') }}</p>

    <form method="POST" action="{{ route('register') }}" novalidate>
        @csrf

        <x-form-input name="name" :label="__('structer.name')" type="text" required autofocus />

        <x-form-input name="email" :label="__('structer.email')" type="email" :placeholder="__('structer.email_placeholder')" required />

        <x-form-input name="password" :label="__('structer.password')" type="password" :placeholder="__('structer.password_placeholder')" required />

        <x-form-input name="password_confirmation" :label="__('structer.confirm_password')" type="password" required />

        <button type="submit" class="btn">{{ __('structer.create_account') }}</button>
    </form>

    <p class="links">
        {{ __('structer.already_have_account') }}
        <a href="{{ route('dashboard') }}">{{ __('structer.sign_in') }}</a>
    </p>
@endsection
