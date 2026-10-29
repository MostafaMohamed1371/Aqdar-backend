@extends('dashboard::layouts.app')

@section('title', __('aqdar.users.add'))
@section('screen', 'ADM-04')

@section('content')
    <div class="ptitle">
        <h2>{{ __('aqdar.users.add') }}</h2>
    </div>

    <div class="card" style="max-width:640px">
        @include('dashboard::users._form', [
            'action' => route('dashboard.users.store'),
            'submit' => __('aqdar.users.add'),
        ])
    </div>
@endsection
