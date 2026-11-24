@extends('dashboard::layouts.app')

@section('title', __('aqdar.guide.title'))
@section('screen', 'GUIDE')

@section('content')
    @php
        $toneFor = static function (string $id): string {
            if (str_starts_with($id, 'flow_')) {
                return 'gold';
            }
            if (str_starts_with($id, 'adm_')) {
                return 'teal';
            }
            if (in_array($id, ['roles', 'mobile'], true)) {
                return 'ink';
            }

            return 'info';
        };
        $iconFor = static function (string $id): string {
            return match (true) {
                $id === 'overview' => 'home',
                $id === 'flow_listing' => 'building',
                $id === 'flow_crm' => 'target',
                $id === 'roles' => 'key',
                $id === 'mobile' => 'globe',
                $id === 'adm_01' => 'home',
                $id === 'adm_02' => 'building',
                $id === 'adm_03' => 'doc',
                $id === 'adm_04' => 'users',
                $id === 'adm_05' => 'db',
                $id === 'adm_06' => 'key',
                $id === 'adm_07' => 'target',
                $id === 'adm_08' => 'inbox',
                $id === 'adm_09' => 'swap',
                $id === 'adm_10' => 'percent',
                $id === 'adm_11' => 'megaphone',
                $id === 'adm_12' => 'chart',
                $id === 'adm_13' => 'settings',
                $id === 'adm_14' => 'list',
                $id === 'adm_15' => 'globe',
                default => 'doc',
            };
        };
    @endphp

    <div class="ptitle guide-ptitle">
        <h2>
            <x-bilingual key="aqdar.guide.title" />
            <small>{{ __('aqdar.guide.intro') }}</small>
        </h2>
        <div class="acts">
            <a class="btn" href="{{ route('dashboard.guide.pdf') }}">
                @include('dashboard::partials.icon', ['name' => 'doc'])
                {{ __('aqdar.guide.download_pdf') }}
            </a>
        </div>
    </div>

    <div class="guide-hero card">
        <div class="guide-hero__mark">
            <span class="guide-hero__icon">@include('dashboard::partials.icon', ['name' => 'doc'])</span>
            <div>
                <b>{{ __('aqdar.guide.hero_title') }}</b>
                <p class="muted">{{ __('aqdar.guide.hero_body') }}</p>
            </div>
        </div>
        <div class="guide-hero__stats">
            <div class="guide-stat">
                <span>{{ __('aqdar.guide.stat_screens') }}</span>
                <b class="en">15</b>
            </div>
            <div class="guide-stat gold">
                <span>{{ __('aqdar.guide.stat_flows') }}</span>
                <b class="en">2</b>
            </div>
            <div class="guide-stat ink">
                <span>{{ __('aqdar.guide.stat_roles') }}</span>
                <b class="en">5</b>
            </div>
        </div>
    </div>

    <nav class="card guide-toc" aria-label="{{ __('aqdar.guide.toc') }}">
        <div class="card-h">
            <b>{{ __('aqdar.guide.toc') }}</b>
            <span class="badge">{{ count($sections) }}</span>
        </div>
        <div class="guide-toc__grid">
            @foreach ($sections as $section)
                @php $tone = $toneFor($section['id']); @endphp
                @php
                    $badgeTone = match ($tone) {
                        'gold' => 'gold',
                        'ink' => 'violet',
                        'info' => 'info',
                        default => '',
                    };
                @endphp
                <a class="guide-toc__item tone-{{ $tone }}" href="#guide-{{ $section['id'] }}">
                    <span class="guide-toc__ic">@include('dashboard::partials.icon', ['name' => $iconFor($section['id'])])</span>
                    <span class="guide-toc__text">
                        @if ($section['screen'])
                            <span @class(['badge', $badgeTone => $badgeTone !== ''])>{{ $section['screen'] }}</span>
                        @else
                            <span @class(['badge', $badgeTone !== '' ? $badgeTone : 'info'])>{{ __('aqdar.guide.flow_badge') }}</span>
                        @endif
                        <strong>{{ $section['title'][$locale] }}</strong>
                    </span>
                </a>
            @endforeach
        </div>
    </nav>

    <div class="guide-stack">
        @foreach ($sections as $section)
            @php
                $tone = $toneFor($section['id']);
                $badgeTone = match ($tone) {
                    'gold' => 'gold',
                    'ink' => 'violet',
                    'info' => 'info',
                    default => '',
                };
                $canOpen = ! empty($section['route']) && \Illuminate\Support\Facades\Route::has($section['route']);
            @endphp
            <section id="guide-{{ $section['id'] }}" class="card guide-section tone-{{ $tone }}">
                <div class="guide-section__bar" aria-hidden="true"></div>
                <div class="guide-section__head">
                    <div class="guide-section__title">
                        <span class="guide-section__ic">@include('dashboard::partials.icon', ['name' => $iconFor($section['id'])])</span>
                        <div>
                            <div class="guide-section__meta">
                                @if ($section['screen'])
                                    <span @class(['badge', $badgeTone => $badgeTone !== ''])>{{ $section['screen'] }}</span>
                                @elseif ($badgeTone !== '')
                                    <span class="badge {{ $badgeTone }}">{{ __('aqdar.guide.flow_badge') }}</span>
                                @endif
                                @if ($section['permission'])
                                    <span class="badge grey en">{{ $section['permission'] }}</span>
                                @endif
                            </div>
                            <h3>{{ $section['title'][$locale] }}</h3>
                        </div>
                    </div>
                    @if ($canOpen)
                        <a class="btn sm ghost" href="{{ route($section['route']) }}">{{ __('aqdar.guide.open_screen') }}</a>
                    @endif
                </div>

                <p class="guide-section__summary">{{ $section['summary'][$locale] }}</p>

                <ol class="guide-steps">
                    @foreach ($section['steps'] as $i => $step)
                        <li>
                            <span class="guide-steps__n en">{{ $i + 1 }}</span>
                            <span class="guide-steps__t">{{ $step[$locale] }}</span>
                        </li>
                    @endforeach
                </ol>
            </section>
        @endforeach
    </div>
@endsection
