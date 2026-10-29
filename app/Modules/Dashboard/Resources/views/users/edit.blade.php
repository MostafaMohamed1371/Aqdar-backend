@extends('dashboard::layouts.app')

@section('title', __('aqdar.users.edit'))
@section('screen', 'ADM-04')

@section('content')
    <div class="ptitle">
        <h2>{{ __('aqdar.users.edit') }}</h2>
    </div>

    <div class="card" style="max-width:640px">
        @include('dashboard::users._form', [
            'action' => route('dashboard.users.update', $user),
            'method' => 'PUT',
            'user' => $user,
            'submit' => __('structer.save'),
        ])
    </div>
@endsection
