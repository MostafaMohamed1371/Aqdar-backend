@extends('dashboard::layouts.app')

@section('title', __('aqdar.menu.adm_15'))
@section('screen', 'ADM-15')

@section('content')
    @include('dashboard::partials.simple-reference', [
        'tab' => 'amenities',
        'routePrefix' => 'dashboard.amenities',
        'records' => $amenities,
        'slugPlaceholder' => 'parking',
    ])
@endsection
