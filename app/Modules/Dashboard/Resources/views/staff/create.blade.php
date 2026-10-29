@extends('dashboard::layouts.app')

@section('title', __('aqdar.staff.add'))
@section('screen', 'ADM-06')

@section('content')
    <div class="ptitle">
        <h2>{{ __('aqdar.staff.add') }}</h2>
    </div>

    <div class="card" style="max-width:640px">
        @include('dashboard::staff._form', [
            'action' => route('dashboard.staff.store'),
            'roles' => $roles,
            'submit' => __('aqdar.staff.add'),
        ])
    </div>
@endsection
