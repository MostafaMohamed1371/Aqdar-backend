@php
    $staffUser = $staffUser ?? null;
    $passwordRequired = $passwordRequired ?? true;
@endphp

<form method="POST" action="{{ $action }}">
    @csrf
    @isset($method)
        @method($method)
    @endisset

    <x-form-input name="name" :label="__('structer.name')" :value="old('name', $staffUser?->name)" required autofocus />
    <x-form-input name="email" :label="__('structer.email')" type="email" :value="old('email', $staffUser?->email)" :placeholder="__('structer.email_placeholder')" required />
    <x-form-input
        name="password"
        :label="__('structer.password')"
        type="password"
        :placeholder="$passwordRequired ? __('structer.password_placeholder') : __('aqdar.staff.password_optional')"
        :required="$passwordRequired"
    />

    <div class="field">
        <label for="role_id">{{ __('aqdar.staff.role') }}</label>
        <div class="inp">
            <select id="role_id" name="role_id" required>
                @foreach ($roles as $role)
                    <option value="{{ $role->id }}" @selected(old('role_id', $staffUser?->role_id) == $role->id)>
                        {{ $role->name_ar }} / {{ $role->name_en }}
                    </option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="acts">
        <button type="submit" class="btn">{{ $submit }}</button>
        <a href="{{ route('dashboard.staff.index') }}" class="btn ghost">{{ __('structer.cancel') }}</a>
    </div>
</form>
