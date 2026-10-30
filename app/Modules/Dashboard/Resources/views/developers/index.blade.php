@extends('dashboard::layouts.app')

@section('title', __('aqdar.menu.adm_05'))
@section('screen', 'ADM-05')

@section('content')
    <div class="ptitle">
        <h2><x-bilingual key="aqdar.menu.adm_05" /></h2>
        <div class="acts">
            <button type="button" class="btn" onclick="const d = document.getElementById('new-record'); d.open = true; d.scrollIntoView({behavior: 'smooth'});">
                @include('dashboard::partials.icon', ['name' => 'plus']){{ $tab === 'projects' ? __('aqdar.catalog.add_project') : __('aqdar.catalog.add_developer') }}
            </button>
        </div>
    </div>

    @include('dashboard::partials.reference-tabs', ['tab' => $tab])

    <details id="new-record" class="card" @if ($errors->any()) open @endif>
        <summary class="card-h"><b>{{ $tab === 'projects' ? __('aqdar.catalog.add_project') : __('aqdar.catalog.add_developer') }}</b></summary>
        @if ($tab === 'projects')
            <form method="POST" action="{{ route('dashboard.projects.store') }}" class="toolbar" style="flex-wrap:wrap">
                @csrf
                <select name="developer_id">
                    <option value="">{{ __('aqdar.catalog.developer') }}</option>
                    @foreach ($developerOptions as $developerOption)
                        <option value="{{ $developerOption->id }}">{{ $developerOption->localizedName() }}</option>
                    @endforeach
                </select>
                <input class="en" name="slug" placeholder="madinaty" required>
                <input name="name_ar" placeholder="{{ __('aqdar.catalog.name_ar') }}" required>
                <input class="en" name="name_en" placeholder="{{ __('aqdar.catalog.name_en') }}" required>
                <label class="check"><input type="checkbox" name="is_other" value="1"> {{ __('aqdar.catalog.other_option') }}</label>
                <button class="btn sm" type="submit">{{ __('structer.save') }}</button>
            </form>
        @else
            <form method="POST" action="{{ route('dashboard.developers.store') }}" enctype="multipart/form-data" class="toolbar" style="flex-wrap:wrap">
                @csrf
                <input class="en" name="slug" placeholder="tmg" required>
                <input name="name_ar" placeholder="{{ __('aqdar.catalog.name_ar') }}" required>
                <input class="en" name="name_en" placeholder="{{ __('aqdar.catalog.name_en') }}" required>
                <input class="en" name="commercial_register" placeholder="{{ __('aqdar.catalog.commercial_register') }}" maxlength="32">
                <label class="tiny">{{ __('aqdar.catalog.registered_on') }} <input type="date" name="registered_on"></label>
                <label class="tiny">{{ __('aqdar.catalog.logo') }} <input type="file" name="logo" accept="image/jpeg,image/png,image/webp"></label>
                <input type="hidden" name="is_verified" value="1">
                <label class="check"><input type="checkbox" name="is_verified" value="0"> {{ __('aqdar.catalog.developer_status.pending') }}</label>
                <label class="check"><input type="checkbox" name="is_other" value="1"> {{ __('aqdar.catalog.other_option') }}</label>
                <button class="btn sm" type="submit">{{ __('structer.save') }}</button>
            </form>
        @endif
    </details>

    <form method="GET" action="{{ route('dashboard.developers.index') }}" class="toolbar">
        @if ($tab === 'projects')
            <input type="hidden" name="tab" value="projects">
        @endif
        <div class="q">
            @include('dashboard::partials.icon', ['name' => 'search'])
            <input type="search" name="q" value="{{ $search }}" placeholder="{{ $tab === 'projects' ? __('aqdar.catalog.search_projects') : __('aqdar.catalog.search_developers') }}">
        </div>
        <button type="submit" class="btn ghost">{{ __('aqdar.search') }}</button>
    </form>

    <div class="tw">
        @if ($tab === 'projects')
            <table class="t">
                <tr>
                    <th>{{ __('structer.name') }}</th>
                    <th>{{ __('aqdar.catalog.developer') }}</th>
                    <th>{{ __('structer.status') }}</th>
                    <th>{{ __('structer.actions') }}</th>
                </tr>
                @forelse ($projects as $project)
                    <tr>
                        <td><b>{{ $project->localizedName() }}</b></td>
                        <td>{{ $project->developer?->localizedName() ?? '—' }}</td>
                        <td>
                            <span @class(['badge', 'ok' => $project->is_active, 'bad' => ! $project->is_active])>
                                {{ $project->is_active ? __('structer.active') : __('structer.inactive') }}
                            </span>
                        </td>
                        <td style="white-space:nowrap">
                            <details style="display:inline-block">
                                <summary class="btn ghost sm">{{ __('structer.edit') }}</summary>
                                <form method="POST" action="{{ route('dashboard.projects.update', $project) }}" class="toolbar" style="flex-wrap:wrap;margin-top:6px">
                                    @csrf @method('PUT')
                                    <input name="name_ar" value="{{ $project->name_ar }}" required>
                                    <input class="en" name="name_en" value="{{ $project->name_en }}" required>
                                    <button class="btn sm" type="submit">{{ __('structer.save') }}</button>
                                </form>
                            </details>
                            @include('dashboard::partials.catalog-toggle', [
                                'action' => route('dashboard.projects.update', $project),
                                'active' => $project->is_active,
                                'locked' => $project->is_other,
                            ])
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="tiny">{{ __('aqdar.catalog.empty') }}</td></tr>
                @endforelse
            </table>
        @else
            <table class="t">
                <tr>
                    <th>{{ __('aqdar.catalog.logo') }}</th>
                    <th>{{ __('structer.name') }}</th>
                    <th>{{ __('aqdar.catalog.commercial_register') }}</th>
                    <th>{{ __('aqdar.catalog.projects') }}</th>
                    <th>{{ __('aqdar.catalog.registered_on') }}</th>
                    <th>{{ __('structer.status') }}</th>
                    <th>{{ __('structer.actions') }}</th>
                </tr>
                @forelse ($developers as $developer)
                    @php $registryStatus = $developer->registryStatus(); @endphp
                    <tr>
                        <td>
                            @if ($developer->logoUrl())
                                <img src="{{ $developer->logoUrl() }}" alt="" width="32" height="32" style="object-fit:contain;border-radius:8px">
                            @else
                                <span class="av s c{{ ($developer->id % 6) + 1 }}">@include('dashboard::partials.icon', ['name' => 'building'])</span>
                            @endif
                        </td>
                        <td><b>{{ $developer->localizedName() }}</b></td>
                        <td class="en">{{ $developer->commercial_register ?? '—' }}</td>
                        <td class="en">{{ $developer->projects_count }}</td>
                        <td class="en">{{ $developer->registered_on?->toDateString() ?? '—' }}</td>
                        <td>
                            <span @class(['badge', 'ok' => $registryStatus === 'approved', 'gold' => $registryStatus === 'pending', 'bad' => $registryStatus === 'suspended'])>
                                {{ __('aqdar.catalog.developer_status.'.$registryStatus) }}
                            </span>
                        </td>
                        <td style="white-space:nowrap">
                            <details style="display:inline-block">
                                <summary class="btn ghost sm">{{ __('structer.edit') }}</summary>
                                <form method="POST" action="{{ route('dashboard.developers.update', $developer) }}" enctype="multipart/form-data" class="toolbar" style="flex-wrap:wrap;margin-top:6px">
                                    @csrf @method('PUT')
                                    <input name="name_ar" value="{{ $developer->name_ar }}" required>
                                    <input class="en" name="name_en" value="{{ $developer->name_en }}" required>
                                    <input class="en" name="commercial_register" value="{{ $developer->commercial_register }}" placeholder="{{ __('aqdar.catalog.commercial_register') }}" maxlength="32">
                                    <input type="date" name="registered_on" value="{{ $developer->registered_on?->toDateString() }}">
                                    <input type="file" name="logo" accept="image/jpeg,image/png,image/webp">
                                    <button class="btn sm" type="submit">{{ __('structer.save') }}</button>
                                </form>
                            </details>
                            @unless ($developer->is_other)
                                <form method="POST" action="{{ route('dashboard.developers.update', $developer) }}" style="display:inline">
                                    @csrf @method('PUT')
                                    <input type="hidden" name="is_verified" value="{{ $developer->is_verified ? '0' : '1' }}">
                                    <button class="btn sm {{ $developer->is_verified ? 'ghost' : '' }}" type="submit">
                                        {{ $developer->is_verified ? __('aqdar.catalog.mark_pending') : __('aqdar.catalog.verify_developer') }}
                                    </button>
                                </form>
                            @endunless
                            @include('dashboard::partials.catalog-toggle', [
                                'action' => route('dashboard.developers.update', $developer),
                                'active' => $developer->is_active,
                                'locked' => $developer->is_other,
                            ])
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="tiny">{{ __('aqdar.catalog.empty') }}</td></tr>
                @endforelse
            </table>
        @endif
    </div>
    {{ ($tab === 'projects' ? $projects : $developers)->links() }}
@endsection
