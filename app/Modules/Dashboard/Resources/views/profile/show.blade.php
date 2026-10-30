@extends('dashboard::layouts.app')

@section('title', __('aqdar.profile.title'))
@section('screen', 'PROFILE')

@section('content')
    <div class="ptitle">
        <h2>{{ __('aqdar.profile.title') }}<small>{{ __('aqdar.profile.subtitle') }}</small></h2>
    </div>

    <form method="POST" action="{{ route('dashboard.profile.update') }}" enctype="multipart/form-data" data-profile-photo>
        @csrf
        @method('PUT')
        <input class="profile-file" id="photo" name="photo" type="file" accept="image/jpeg,image/png,image/webp">

        <div class="cols2e profile-grid">
            <div class="card">
                <div class="card-h"><b>{{ __('aqdar.profile.details') }}</b></div>
                <div class="profile-hero">
                    <label class="profile-photo" for="photo">
                        <span data-profile-photo-preview="hero">
                            @include('dashboard::partials.staff-avatar', ['staff' => $staffUser, 'size' => 'xl'])
                        </span>
                        <span class="cam" aria-hidden="true">@include('dashboard::partials.icon', ['name' => 'camera', 'size' => 'xs'])</span>
                    </label>
                    <div>
                        <b>{{ $staffUser->name }}</b>
                        <span class="en">{{ $staffUser->email }}</span>
                        <div class="badges">
                            <span class="badge">{{ $staffUser->roleName() }}</span>
                            @if ($staffUser->is_active)
                                <span class="badge ok">{{ __('aqdar.users.active_badge') }}</span>
                            @else
                                <span class="badge bad">{{ __('aqdar.staff.access_revoked') }}</span>
                            @endif
                        </div>
                    </div>
                </div>
                <div class="kv"><span>{{ __('aqdar.staff.role') }}</span><b>{{ $staffUser->roleName() }}</b></div>
                <div class="kv"><span>{{ __('aqdar.staff.branch') }}</span><b>{{ $staffUser->branch ?? '—' }}</b></div>
                <div class="kv">
                    <span>{{ __('aqdar.profile.status') }}</span>
                    <b>
                        @if ($staffUser->is_active)
                            {{ __('aqdar.users.active_badge') }}
                        @else
                            {{ __('aqdar.staff.access_revoked') }}
                        @endif
                    </b>
                </div>
                <div class="kv">
                    <span>{{ __('aqdar.profile.last_login') }}</span>
                    <b class="en">{{ $staffUser->last_login_at?->format('Y/m/d H:i') ?? '—' }}</b>
                </div>
            </div>

            <div class="card">
                <div class="card-h"><b>{{ __('aqdar.profile.edit') }}</b></div>

                <div class="field">
                    <label for="photo">{{ __('aqdar.profile.photo') }}</label>
                    <label class="upload profile-upload" for="photo">
                        <span data-profile-photo-preview="tile">
                            @if ($staffUser->avatarUrl())
                                <img class="av l" src="{{ $staffUser->avatarUrl() }}" alt="" width="72" height="72">
                            @else
                                @include('dashboard::partials.icon', ['name' => 'image', 'size' => 'l'])
                            @endif
                        </span>
                        @include('dashboard::partials.icon', ['name' => 'upload'])
                        {{ __('aqdar.profile.change_photo') }}
                        <small>{{ __('aqdar.profile.photo_hint') }}</small>
                        <span class="en" data-profile-photo-name></span>
                    </label>
                    @error('photo')
                        <p class="hint" style="color:var(--bad)">{{ $message }}</p>
                    @enderror
                    @if ($staffUser->avatarUrl())
                        <label class="check">
                            <input type="checkbox" name="remove_photo" value="1">
                            {{ __('aqdar.profile.remove_photo') }}
                        </label>
                    @endif
                </div>

                <x-form-input name="name" :label="__('structer.name')" :value="old('name', $staffUser->name)" required autofocus />
                <x-form-input name="email" :label="__('structer.email')" type="email" :value="old('email', $staffUser->email)" required />

                @can('manage_staff')
                    <div class="field">
                        <label for="role_id">{{ __('aqdar.staff.role') }}</label>
                        <div class="inp">
                            <select id="role_id" name="role_id" required>
                                @foreach ($roles as $role)
                                    <option value="{{ $role->id }}" @selected(old('role_id', $staffUser->role_id) == $role->id)>
                                        {{ $role->name_ar }} / {{ $role->name_en }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        @error('role_id')
                            <p class="hint" style="color:var(--bad)">{{ $message }}</p>
                        @enderror
                    </div>
                @endcan
                <x-form-input
                    name="password"
                    :label="__('structer.new_password')"
                    type="password"
                    :placeholder="__('aqdar.staff.password_optional')"
                    :value="''"
                />
                <x-form-input
                    name="password_confirmation"
                    :label="__('structer.confirm_password')"
                    type="password"
                    :value="''"
                />

                <div class="acts">
                    <button type="submit" class="btn">{{ __('structer.save') }}</button>
                    <a href="{{ route('dashboard') }}" class="btn ghost">{{ __('structer.cancel') }}</a>
                </div>
            </div>
        </div>
    </form>
@endsection
