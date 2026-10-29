@extends('dashboard::layouts.app')

@section('title', __('structer.add_category'))
@section('screen', 'ADM-15')

@section('content')
    <div class="ptitle">
        <h2>{{ __('structer.add_category') }}</h2>
    </div>

    <div class="card" style="max-width:640px">
        <form method="POST" action="{{ route('dashboard.categories.store') }}">
            @csrf

            <x-form-input name="name_ar" :label="__('aqdar.catalog.name_ar')" :value="old('name_ar')" required autofocus />
            <x-form-input name="name_en" :label="__('aqdar.catalog.name_en')" :value="old('name_en')" />
            <x-form-input name="slug" :label="__('structer.slug')" :value="old('slug')" :placeholder="__('structer.slug_placeholder')" required />

            <div class="field">
                <label for="parent_id">{{ __('structer.parent_category') }}</label>
                <div class="inp">
                    <select id="parent_id" name="parent_id">
                        <option value="">{{ __('structer.no_parent') }}</option>
                        @foreach ($parentCategories as $parent)
                            <option value="{{ $parent->id }}" @selected(old('parent_id') == $parent->id)>{{ $parent->localizedName() }}</option>
                        @endforeach
                    </select>
                </div>
                @error('parent_id')
                    <p class="hint" style="color:var(--bad)">{{ $message }}</p>
                @enderror
            </div>

            <div class="field">
                <label for="help_ar">{{ __('aqdar.catalog.help_ar') }}</label>
                <textarea id="help_ar" name="help_ar" rows="3" style="width:100%">{{ old('help_ar') }}</textarea>
            </div>
            <div class="field">
                <label for="help_en">{{ __('aqdar.catalog.help_en') }}</label>
                <textarea id="help_en" name="help_en" rows="3" class="en" style="width:100%">{{ old('help_en') }}</textarea>
            </div>

            <x-form-input name="commission_policy_code" :label="__('aqdar.catalog.commission_policy_code')" :value="old('commission_policy_code')" class="en" />

            <x-form-input name="sort_order" type="number" :label="__('structer.sort_order')" :value="old('sort_order', 0)" />

            <label class="check">
                <input type="hidden" name="is_active" value="0">
                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', true))>
                {{ __('structer.category_active') }}
            </label>

            <div class="acts">
                <button type="submit" class="btn">{{ __('structer.save') }}</button>
                <a href="{{ route('dashboard.categories.index') }}" class="btn ghost">{{ __('structer.cancel') }}</a>
            </div>
        </form>
    </div>
@endsection
