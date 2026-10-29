@extends('dashboard::layouts.app')

@section('title', __('aqdar.menu.adm_05'))
@section('screen', 'ADM-05')

@section('content')
    <div class="ptitle">
        <h2><x-bilingual key="aqdar.menu.adm_05" /></h2>
    </div>

    <div class="cols2e">
        <div class="tw">
            <h3>{{ __('aqdar.catalog.developers') }}</h3>
            <form method="POST" action="{{ route('dashboard.developers.store') }}" class="toolbar" style="flex-wrap:wrap">
                @csrf
                <input class="en" name="slug" placeholder="tmg" required>
                <input name="name_ar" placeholder="{{ __('aqdar.catalog.name_ar') }}" required>
                <input class="en" name="name_en" placeholder="{{ __('aqdar.catalog.name_en') }}" required>
                <label class="check"><input type="checkbox" name="is_other" value="1"> {{ __('aqdar.catalog.other_option') }}</label>
                <button class="btn sm" type="submit">{{ __('structer.save') }}</button>
            </form>
            <table class="t">
                <tr>
                    <th>{{ __('structer.name') }}</th>
                    <th>{{ __('aqdar.catalog.projects') }}</th>
                    <th>{{ __('structer.status') }}</th>
                    <th>{{ __('structer.actions') }}</th>
                </tr>
                @foreach ($developers as $developer)
                    <tr>
                        <td>
                            <form method="POST" action="{{ route('dashboard.developers.update', $developer) }}" class="toolbar" style="flex-wrap:wrap">
                                @csrf @method('PUT')
                                <input name="name_ar" value="{{ $developer->name_ar }}" required>
                                <input class="en" name="name_en" value="{{ $developer->name_en }}" required>
                                <button class="btn ghost sm" type="submit">{{ __('structer.save') }}</button>
                            </form>
                        </td>
                        <td class="en">{{ $developer->projects_count }}</td>
                        <td>
                            @if ($developer->is_active)
                                <span class="badge ok">{{ __('structer.active') }}</span>
                            @else
                                <span class="badge grey">{{ __('structer.inactive') }}</span>
                            @endif
                        </td>
                        <td>
                            @include('dashboard::partials.catalog-toggle', [
                                'action' => route('dashboard.developers.update', $developer),
                                'active' => $developer->is_active,
                                'locked' => $developer->is_other,
                            ])
                        </td>
                    </tr>
                @endforeach
            </table>
        </div>
        <div class="tw">
            <h3>{{ __('aqdar.catalog.projects') }}</h3>
            <form method="POST" action="{{ route('dashboard.projects.store') }}" class="toolbar" style="flex-wrap:wrap">
                @csrf
                <select name="developer_id">
                    <option value="">—</option>
                    @foreach ($developers as $developer)
                        <option value="{{ $developer->id }}">{{ $developer->localizedName() }}</option>
                    @endforeach
                </select>
                <input class="en" name="slug" placeholder="madinaty" required>
                <input name="name_ar" placeholder="{{ __('aqdar.catalog.name_ar') }}" required>
                <input class="en" name="name_en" placeholder="{{ __('aqdar.catalog.name_en') }}" required>
                <label class="check"><input type="checkbox" name="is_other" value="1"> {{ __('aqdar.catalog.other_option') }}</label>
                <button class="btn sm" type="submit">{{ __('structer.save') }}</button>
            </form>
            <table class="t">
                @foreach ($projects as $project)
                    <tr>
                        <td>
                            <form method="POST" action="{{ route('dashboard.projects.update', $project) }}" class="toolbar" style="flex-wrap:wrap">
                                @csrf @method('PUT')
                                <input name="name_ar" value="{{ $project->name_ar }}" required>
                                <input class="en" name="name_en" value="{{ $project->name_en }}" required>
                                <button class="btn ghost sm" type="submit">{{ __('structer.save') }}</button>
                            </form>
                            <div class="tiny">{{ $project->developer?->localizedName() }}</div>
                        </td>
                        <td>
                            @include('dashboard::partials.catalog-toggle', [
                                'action' => route('dashboard.projects.update', $project),
                                'active' => $project->is_active,
                                'locked' => $project->is_other,
                            ])
                        </td>
                    </tr>
                @endforeach
            </table>
        </div>
    </div>
@endsection
