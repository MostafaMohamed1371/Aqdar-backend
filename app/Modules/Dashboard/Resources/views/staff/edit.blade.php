@extends('dashboard::layouts.app')

@section('title', __('aqdar.staff.edit'))
@section('screen', 'ADM-06')

@section('content')
    <div class="ptitle">
        <h2>{{ __('aqdar.staff.edit') }}</h2>
    </div>

    <div class="card" style="max-width:640px">
        @include('dashboard::staff._form', [
            'action' => route('dashboard.staff.update', $staffUser),
            'method' => 'PUT',
            'staffUser' => $staffUser,
            'roles' => $roles,
            'passwordRequired' => false,
            'submit' => __('structer.save'),
        ])
    </div>
@endsection
