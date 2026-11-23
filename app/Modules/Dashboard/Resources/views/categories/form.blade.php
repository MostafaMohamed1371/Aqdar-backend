@extends('dashboard::layouts.app')

@section('title', __('aqdar.forms.title'))
@section('screen', 'ADM-15')

@section('content')
    <div class="ptitle">
        <h2>{{ __('aqdar.forms.title') }} · {{ $category->localizedName() }}</h2>
    </div>

    <p class="tiny">{{ __('aqdar.forms.intro') }}</p>
    <p class="tiny">{{ __('aqdar.forms.current_version', ['version' => (int) $category->form_version]) }} · {{ __('aqdar.forms.add_hint') }}</p>

    <div class="card">
        <form method="POST" action="{{ route('dashboard.categories.form.update', $category) }}">
            @csrf
            @method('PUT')

            @php
                $rows = $fields->map(fn ($field): array => [
                    'key' => $field->field_key,
                    'label_ar' => $field->label_ar,
                    'label_en' => $field->label_en,
                    'type' => $field->type?->value ?? 'text',
                    'options' => implode(', ', $field->options ?? []),
                    'is_required' => (bool) $field->is_required,
                ])->values()->all();
                for ($spare = 0; $spare < 3; $spare++) {
                    $rows[] = ['key' => '', 'label_ar' => '', 'label_en' => '', 'type' => 'text', 'options' => '', 'is_required' => false];
                }
            @endphp

            <div class="tw">
                <table class="t">
                    <tr>
                        <th>{{ __('aqdar.forms.col_key') }}</th>
                        <th>{{ __('aqdar.forms.col_label_ar') }}</th>
                        <th>{{ __('aqdar.forms.col_label_en') }}</th>
                        <th>{{ __('aqdar.forms.col_type') }}</th>
                        <th>{{ __('aqdar.forms.col_options') }}</th>
                        <th>{{ __('aqdar.forms.col_required') }}</th>
                    </tr>
                    @foreach ($rows as $index => $row)
                        <tr>
                            <td>
                                <input name="fields[{{ $index }}][key]" value="{{ old("fields.$index.key", $row['key']) }}" class="en" maxlength="64">
                                @error("fields.$index.key")
                                    <p class="hint" style="color:var(--bad)">{{ $message }}</p>
                                @enderror
                            </td>
                            <td>
                                <input name="fields[{{ $index }}][label_ar]" value="{{ old("fields.$index.label_ar", $row['label_ar']) }}" maxlength="191">
                                @error("fields.$index.label_ar")
                                    <p class="hint" style="color:var(--bad)">{{ $message }}</p>
                                @enderror
                            </td>
                            <td>
                                <input name="fields[{{ $index }}][label_en]" value="{{ old("fields.$index.label_en", $row['label_en']) }}" class="en" maxlength="191">
                            </td>
                            <td>
                                <select name="fields[{{ $index }}][type]">
                                    @foreach ($types as $type)
                                        <option value="{{ $type->value }}" @selected(old("fields.$index.type", $row['type']) === $type->value)>{{ __('aqdar.forms.type.'.$type->value) }}</option>
                                    @endforeach
                                </select>
                                @error("fields.$index.type")
                                    <p class="hint" style="color:var(--bad)">{{ $message }}</p>
                                @enderror
                            </td>
                            <td>
                                <input name="fields[{{ $index }}][options]" value="{{ old("fields.$index.options", $row['options']) }}" maxlength="1000">
                                @error("fields.$index.options")
                                    <p class="hint" style="color:var(--bad)">{{ $message }}</p>
                                @enderror
                            </td>
                            <td>
                                <input type="checkbox" name="fields[{{ $index }}][is_required]" value="1" @checked(old("fields.$index.is_required", $row['is_required']))>
                            </td>
                        </tr>
                    @endforeach
                </table>
            </div>

            <div class="acts">
                <button type="submit" class="btn">{{ __('aqdar.forms.publish') }}</button>
                <a href="{{ route('dashboard.categories.edit', $category) }}" class="btn ghost">{{ __('structer.cancel') }}</a>
            </div>
        </form>
    </div>
@endsection
