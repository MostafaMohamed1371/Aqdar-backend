@extends('auth::layouts.guest')

@section('title', __('structer.reset_password'))

@section('content')
    <h1>{{ __('structer.reset_password') }}</h1>
    <p class="sub">{{ __('structer.reset_password_hint') }}</p>

    <form method="POST" action="{{ route('password.update') }}" novalidate>
        @csrf

        <input type="hidden" name="token" value="{{ $token }}">

        <x-form-input name="email" :label="__('structer.email')" type="email" :value="old('email', $email)" required autofocus />

        <x-form-input name="password" :label="__('structer.new_password')" type="password" :placeholder="__('structer.password_placeholder')" required />

        <x-form-input name="password_confirmation" :label="__('structer.confirm_password')" type="password" required />

        <button type="submit" class="btn">{{ __('structer.reset_password') }}</button>
    </form>
@endsection
