@php
    use App\Core\Enums\AccountType;
    $user = $user ?? null;
@endphp

<form method="POST" action="{{ $action }}">
    @csrf
    @isset($method)
        @method($method)
    @endisset

    <x-form-input name="name" :label="__('structer.name')" :value="old('name', $user?->name)" required autofocus />
    <x-form-input name="email" :label="__('structer.email')" type="email" :value="old('email', $user?->email)" :placeholder="__('structer.email_placeholder')" />
    <x-form-input name="phone" :label="__('aqdar.users.phone')" :value="old('phone', $user?->phone)" class="en" />

    <div class="field">
        <label for="account_type">{{ __('aqdar.users.type') }}</label>
        <div class="inp">
            <select id="account_type" name="account_type">
                @foreach (AccountType::cases() as $type)
                    <option value="{{ $type->value }}" @selected(old('account_type', $user?->account_type?->value ?? 'individual') === $type->value)>
                        {{ $type->labelAr() }}
                    </option>
                @endforeach
            </select>
        </div>
    </div>

    <label class="check">
        <input type="hidden" name="is_buyer" value="0">
        <input type="checkbox" name="is_buyer" value="1" @checked(old('is_buyer', $user?->is_buyer ?? true))>
        {{ __('aqdar.users.buyer') }}
    </label>

    <label class="check">
        <input type="hidden" name="is_seller" value="0">
        <input type="checkbox" name="is_seller" value="1" @checked(old('is_seller', $user?->is_seller ?? false))>
        {{ __('aqdar.users.also_seller') }}
    </label>

    <div class="acts">
        <button type="submit" class="btn">{{ $submit }}</button>
        <a href="{{ route('dashboard.users.index') }}" class="btn ghost">{{ __('structer.cancel') }}</a>
    </div>
</form>
