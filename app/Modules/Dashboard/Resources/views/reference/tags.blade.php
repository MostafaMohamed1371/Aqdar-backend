@extends('dashboard::layouts.app')

@section('title', __('aqdar.menu.adm_15'))
@section('screen', 'ADM-15')

@section('content')
    @include('dashboard::partials.simple-reference', [
        'tab' => 'tags',
        'routePrefix' => 'dashboard.tags',
        'records' => $tags,
        'slugPlaceholder' => 'featured',
    ])
@endsection
