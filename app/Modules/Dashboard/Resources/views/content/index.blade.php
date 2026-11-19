@extends('dashboard::layouts.app')

@section('title', __('aqdar.menu.adm_11'))
@section('screen', 'ADM-11')

@section('content')
    @php
        $tab = $tab ?: 'templates';
        $tabs = [
            'templates' => __('aqdar.notifications.tabs.templates'),
            'push' => __('aqdar.notifications.tabs.push'),
            'announcements' => __('aqdar.notifications.tabs.announcements'),
            'banners' => __('aqdar.catalog.banners'),
            'faqs' => __('aqdar.catalog.faqs'),
            'categories' => __('aqdar.notifications.tabs.categories'),
            'support' => __('aqdar.catalog.support'),
            'onboarding' => __('aqdar.catalog.onboarding'),
            'seller_guide' => __('aqdar.catalog.seller_guide'),
            'schedule' => __('aqdar.notifications.tabs.schedule'),
            'log' => __('aqdar.notifications.tabs.log'),
        ];
    @endphp
    <div class="ptitle">
        <h2><x-bilingual key="aqdar.menu.adm_11" /></h2>
        <div class="acts">
            @can('manage_catalog')
                <a class="btn" href="#add-content">@include('dashboard::partials.icon', ['name' => 'plus']){{ __('aqdar.notifications.create') }}</a>
            @endcan
        </div>
    </div>
    <p class="tiny">{{ __('aqdar.notifications.copy_note') }}</p>

    <div class="ftabs">
        @foreach ($tabs as $key => $label)
            <a href="{{ route('dashboard.content.index', ['tab' => $key]) }}" @class(['on' => $tab === $key]) style="text-decoration:none;color:inherit"><span @class(['on' => $tab === $key])>{{ $label }}</span></a>
        @endforeach
    </div>

    @if (in_array($tab, ['templates', 'push', 'announcements', 'schedule'], true))
        <div class="cols-side">
            @if ($tab !== 'announcements' && auth()->user()?->can('manage_catalog'))
            <div class="card" style="margin:0">
                <div class="card-h"><b>{{ __('aqdar.notifications.tabs.templates') }}</b></div>
                @forelse ($templates as $template)
                    <details style="padding:6px 0;border-bottom:1px dashed var(--line)">
                        <summary class="dl" style="padding:4px 0;align-items:center;cursor:pointer;list-style:none">
                            <span><b>{{ app()->getLocale() === 'en' ? $template->title_en : $template->title_ar }}</b> <span class="tiny en">{{ $template->key }}</span></span>
                            <span style="display:flex;gap:6px;align-items:center">
                                <span class="badge {{ $template->is_operational ? 'ok' : 'gold' }}">{{ $template->is_operational ? __('aqdar.notifications.operational') : __('aqdar.notifications.marketing') }}</span>
                                <span class="icon-btn" title="{{ __('structer.edit') }}">@include('dashboard::partials.icon', ['name' => 'edit'])</span>
                            </span>
                        </summary>
                        <form method="POST" action="{{ route('dashboard.notifications.templates.update', $template) }}">
                            @csrf
                            @method('PUT')
                            <input name="title_ar" value="{{ $template->title_ar }}" style="width:100%;margin:4px 0">
                            <input class="en" name="title_en" value="{{ $template->title_en }}" style="width:100%;margin:4px 0">
                            <textarea name="body_ar" rows="2" style="width:100%;margin:4px 0">{{ $template->body_ar }}</textarea>
                            <textarea class="en" name="body_en" rows="2" style="width:100%;margin:4px 0">{{ $template->body_en }}</textarea>
                            <button class="btn ghost sm" type="submit">{{ __('structer.save') }}</button>
                        </form>
                    </details>
                @empty
                    <p class="tiny">{{ __('aqdar.notifications.empty_templates') }}</p>
                @endforelse
                <p class="tiny" style="margin:8px 0 0">{{ __('aqdar.notifications.copy_note') }}</p>
            </div>
            @endif
            @php($fcm = $fcmStatus ?? ['ready' => false, 'label' => '', 'driver' => 'log', 'checklist' => [], 'project_id' => null, 'devices_with_token' => 0, 'push_pending' => 0, 'push_failed' => 0, 'queue' => ''])
            <div class="card" style="margin:0 0 16px">
                <div class="card-h">
                    <b>{{ __('aqdar.notifications.fcm_status_title') }}</b>
                    <span class="badge {{ ! empty($fcm['ready']) ? 'ok' : 'warn' }}">{{ $fcm['label'] }}</span>
                </div>
                <div class="dl"><span>{{ __('aqdar.notifications.fcm_driver') }}</span><b class="en">{{ $fcm['driver'] }}</b></div>
                <div class="dl"><span>{{ __('aqdar.notifications.fcm_project') }}</span><b class="en">{{ $fcm['project_id'] ?: '—' }}</b></div>
                <div class="dl"><span>{{ __('aqdar.notifications.fcm_queue') }}</span><b class="en">{{ $fcm['queue'] }}</b></div>
                <div class="dl"><span>{{ __('aqdar.notifications.fcm_devices') }}</span><b class="en">{{ number_format($fcm['devices_with_token']) }}</b></div>
                <div class="dl"><span>{{ __('aqdar.settings.diag_push_pending') }}</span><b class="en">{{ number_format($fcm['push_pending']) }}</b></div>
                <div class="dl"><span>{{ __('aqdar.settings.diag_push_failed') }}</span><b class="en" @style(['color:var(--bad)' => ($fcm['push_failed'] ?? 0) > 0])>{{ number_format($fcm['push_failed']) }}</b></div>
                <ul class="bul" style="margin-top:10px">
                    @foreach (($fcm['checklist'] ?? []) as $item)
                        <li style="color:{{ $item['ok'] ? 'var(--ok)' : 'var(--warn)' }}">{{ $item['ok'] ? '✓' : '!' }} {{ $item['label'] }}</li>
                    @endforeach
                </ul>
                <p class="tiny" style="margin-top:8px">{{ __('aqdar.notifications.fcm_setup_hint') }}</p>
                @can('manage_catalog')
                    <form method="POST" action="{{ route('dashboard.notifications.fcm-verify') }}" style="margin-top:10px">
                        @csrf
                        <button class="btn ghost" type="submit">{{ __('aqdar.notifications.fcm_verify') }}</button>
                    </form>
                @endcan
            </div>
            <div class="card" style="margin:0" id="add-content">
                <div class="card-h"><b>{{ __('aqdar.notifications.send_push') }}</b></div>
                <form method="POST" action="{{ route('dashboard.notifications.broadcasts') }}">
                    @csrf
                    <div class="field">
                        <label for="broadcast-title-ar">{{ __('aqdar.notifications.title') }}</label>
                        <div class="inp"><input id="broadcast-title-ar" name="title_ar" required value="{{ old('title_ar', __('aqdar.notifications.fcm_default_title')) }}"></div>
                    </div>
                    <div class="field">
                        <label for="broadcast-title-en">{{ __('aqdar.catalog.title_en') }}</label>
                        <div class="inp"><input id="broadcast-title-en" class="en" name="title_en" value="{{ old('title_en', 'AQDAR test') }}"></div>
                    </div>
                    <div class="field">
                        <label for="broadcast-body-ar">{{ __('aqdar.notifications.body') }}</label>
                        <div class="inp"><textarea id="broadcast-body-ar" name="body_ar" rows="3" required>{{ old('body_ar', __('aqdar.notifications.fcm_default_body')) }}</textarea></div>
                    </div>
                    <div class="field">
                        <label for="broadcast-body-en">{{ __('aqdar.catalog.body_en') }}</label>
                        <div class="inp"><textarea id="broadcast-body-en" class="en" name="body_en" rows="2" placeholder="{{ __('aqdar.catalog.body_en') }}">{{ old('body_en', 'If you see this, FCM delivery works.') }}</textarea></div>
                    </div>
                    <div class="field">
                        <label for="broadcast-audience">{{ __('aqdar.notifications.audience') }}</label>
                        <div class="inp">
                            <select id="broadcast-audience" name="audience">
                                <option value="marketing_opted_in">{{ __('aqdar.notifications.opted_in') }}</option>
                            </select>
                        </div>
                    </div>
                    <div class="field">
                        <label for="broadcast-scheduled-at">{{ __('aqdar.notifications.scheduled_at') }}</label>
                        <div class="inp"><input id="broadcast-scheduled-at" class="en" type="datetime-local" name="scheduled_at" value="{{ old('scheduled_at') }}"></div>
                    </div>
                    <div style="display:flex;gap:8px;margin-top:8px;flex-wrap:wrap">
                        @can('manage_catalog')
                            <button class="btn ghost" type="submit" formaction="{{ route('dashboard.notifications.test-send') }}" style="flex:1;justify-content:center">{{ __('aqdar.notifications.test_staff') }}</button>
                            <button class="btn ghost" type="submit" formaction="{{ route('dashboard.notifications.fcm-diagnostic') }}" style="flex:1;justify-content:center">{{ __('aqdar.notifications.fcm_diagnostic') }}</button>
                        @endcan
                        <button class="btn" type="submit" style="flex:1;justify-content:center">{{ __('aqdar.notifications.schedule') }}</button>
                    </div>
                    <p class="tiny" style="margin-top:8px">{{ __('aqdar.notifications.fcm_diagnostic_hint') }}</p>
                </form>
            </div>
        </div>
    @endif

    @if (auth()->user()?->can('manage_catalog') && (in_array($tab, ['banners', 'faqs', 'support', 'onboarding', 'seller_guide'], true) || $tab === 'templates'))
        @if ($tab !== 'templates' && $tab !== 'push')
            <form id="add-content" method="POST" action="{{ route('dashboard.content.store') }}" class="tw" style="padding:16px;margin-bottom:16px" enctype="multipart/form-data">
                @csrf
                <div class="toolbar" style="flex-wrap:wrap">
                    <select name="type" required>
                        <option value="banner" @selected($tab === 'banners')>{{ __('aqdar.catalog.banners') }}</option>
                        <option value="faq" @selected($tab === 'faqs')>{{ __('aqdar.catalog.faqs') }}</option>
                        <option value="support">{{ __('aqdar.catalog.support') }}</option>
                        <option value="onboarding" @selected($tab === 'onboarding')>{{ __('aqdar.catalog.onboarding') }}</option>
                        <option value="seller_guide" @selected($tab === 'seller_guide')>{{ __('aqdar.catalog.seller_guide') }}</option>
                    </select>
                    <input class="en" name="key" placeholder="home-hero">
                    <input name="title_ar" placeholder="{{ __('aqdar.catalog.title_ar') }}">
                    <input class="en" name="title_en" placeholder="{{ __('aqdar.catalog.title_en') }}">
                    <input class="en" name="link_url" placeholder="{{ __('aqdar.catalog.link_url') }}">
                    <input type="number" name="sort_order" min="0" value="0" style="width:6rem" title="{{ __('aqdar.catalog.sort_order') }}">
                    <input type="file" name="image" accept="image/jpeg,image/png,image/webp">
                </div>
                <textarea name="body_ar" rows="3" placeholder="{{ __('aqdar.catalog.body_ar') }}" style="width:100%;margin:8px 0"></textarea>
                <textarea name="body_en" rows="3" placeholder="{{ __('aqdar.catalog.body_en') }}" class="en" style="width:100%;margin:8px 0"></textarea>
                <button class="btn" type="submit">{{ __('structer.save') }}</button>
            </form>
            @foreach (['banners' => $banners, 'faqs' => $faqs, 'support' => $support, 'onboarding' => $onboarding, 'seller_guide' => $sellerGuide] as $label => $blocks)
                @if ($tab === $label)
                    <div class="tw" style="margin-bottom:16px">
                        <h3>{{ __('aqdar.catalog.'.$label) }}</h3>
                        <table class="t">
                            @forelse ($blocks as $block)
                                <tr>
                                    <td>
                                        <b>{{ app()->getLocale() === 'en' ? $block->title_en : $block->title_ar }}</b>
                                        <div class="tiny en">{{ $block->key }}</div>
                                    </td>
                                    <td class="en">{{ $block->sort_order }}</td>
                                    <td>
                                        <details>
                                            <summary class="icon-btn" title="{{ __('structer.edit') }}" style="list-style:none;cursor:pointer">@include('dashboard::partials.icon', ['name' => 'edit'])</summary>
                                            <form method="POST" action="{{ route('dashboard.content.update', $block) }}" enctype="multipart/form-data" style="margin-top:6px">
                                                @csrf @method('PUT')
                                                <input name="title_ar" value="{{ $block->title_ar }}">
                                                <input class="en" name="title_en" value="{{ $block->title_en }}">
                                                <input class="en" name="link_url" value="{{ $block->link_url }}" placeholder="{{ __('aqdar.catalog.link_url') }}">
                                                <input type="number" name="sort_order" min="0" value="{{ $block->sort_order }}" style="width:6rem">
                                                @if (in_array($label, ['banners', 'onboarding'], true))
                                                    <input type="file" name="image" accept="image/jpeg,image/png,image/webp">
                                                @endif
                                                <textarea name="body_ar" rows="2" style="width:100%;margin:8px 0">{{ $block->body_ar }}</textarea>
                                                <textarea name="body_en" rows="2" class="en" style="width:100%;margin:8px 0">{{ $block->body_en }}</textarea>
                                                <button class="btn ghost sm" type="submit">{{ __('structer.save') }}</button>
                                            </form>
                                        </details>
                                    </td>
                                    <td>
                                        @include('dashboard::partials.catalog-toggle', [
                                            'action' => route('dashboard.content.update', $block),
                                            'active' => $block->is_active,
                                        ])
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="empty-note">{{ __('aqdar.catalog.empty') }}</td></tr>
                            @endforelse
                        </table>
                    </div>
                @endif
            @endforeach
        @endif
    @endif

    @if ($tab === 'schedule')
        <div class="card">
            <div class="card-h"><b>{{ __('aqdar.notifications.tabs.schedule') }}</b></div>
            <table class="t">
                <tr>
                    <th>{{ __('aqdar.notifications.title') }}</th>
                    <th>{{ __('aqdar.notifications.audience') }}</th>
                    <th>{{ __('structer.status') }}</th>
                    <th>{{ __('aqdar.notifications.scheduled_at') }}</th>
                    <th>{{ __('aqdar.notifications.usage') }}</th>
                    <th>{{ __('aqdar.users.actions') }}</th>
                </tr>
                @forelse ($broadcasts as $row)
                    <tr>
                        <td>{{ $row->title_ar }}</td>
                        <td>{{ __('aqdar.notifications.opted_in') }}</td>
                        <td>
                            {{ $row->status }}
                            @if ($row->approved_at)
                                <span class="badge ok">{{ __('aqdar.notifications.approved_badge') }}</span>
                            @else
                                <span class="badge gold">{{ __('aqdar.notifications.pending_badge') }}</span>
                            @endif
                        </td>
                        <td class="en">{{ optional($row->scheduled_at)->format('Y-m-d H:i') }}</td>
                        <td class="en">{{ optional($row->sent_at)->format('Y-m-d H:i') ?: '—' }}</td>
                        <td>
                            @can('approve_broadcasts')
                                @if (! $row->approved_at && ((int) $row->created_by !== (int) auth()->id() || auth()->user()?->isSuperAdmin()))
                                    <form method="POST" action="{{ route('dashboard.notifications.broadcasts.approve', $row) }}" style="display:inline">
                                        @csrf
                                        <button class="btn sm" type="submit">{{ __('aqdar.notifications.approve_broadcast') }}</button>
                                    </form>
                                @endif
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6">{{ __('aqdar.notifications.empty_broadcasts') }}</td></tr>
                @endforelse
            </table>
        </div>
    @endif

    @if ($tab === 'categories')
        <div class="card">
            <div class="card-h"><b>{{ __('aqdar.notifications.tabs.categories') }}</b></div>
            <table class="t">
                <tr>
                    <th>{{ __('aqdar.catalog.name_ar') }}</th>
                    <th>{{ __('aqdar.catalog.help_ar') }}</th>
                    <th>{{ __('aqdar.catalog.help_en') }}</th>
                    <th></th>
                </tr>
                @forelse ($categories as $category)
                    <tr>
                        <td>{{ $category->name_ar ?: $category->name }}</td>
                        <td>{{ \Illuminate\Support\Str::limit((string) $category->help_ar, 80) }}</td>
                        <td class="en">{{ \Illuminate\Support\Str::limit((string) $category->help_en, 80) }}</td>
                        <td><a class="btn ghost sm" href="{{ route('dashboard.categories.edit', $category) }}">{{ __('structer.edit') }}</a></td>
                    </tr>
                @empty
                    <tr><td colspan="4">{{ __('aqdar.notifications.empty_categories') }}</td></tr>
                @endforelse
            </table>
        </div>
    @endif

    @if (in_array($tab, ['log', 'templates', 'push', 'announcements'], true))
        <div class="card">
            <div class="card-h"><b>{{ __('aqdar.notifications.tabs.log') }}</b><span class="tiny">{{ __('aqdar.notifications.log_note') }}</span></div>
            <div class="tw">
                <table class="t">
                    <tr>
                        <th>{{ __('aqdar.notifications.event') }}</th>
                        <th>{{ __('aqdar.notifications.template') }}</th>
                        <th>{{ __('aqdar.notifications.recipient') }}</th>
                        <th>{{ __('aqdar.notifications.channel') }}</th>
                        <th>{{ __('aqdar.notifications.attempts') }}</th>
                        <th>{{ __('aqdar.notifications.provider') }}</th>
                        <th>{{ __('structer.status') }}</th>
                    </tr>
                    @forelse ($deliveries as $row)
                        <tr>
                            <td class="en">{{ $row->notification?->event_key }}</td>
                            <td class="en">{{ $row->notification?->template_key }}</td>
                            <td class="en">USR-{{ $row->notification?->notifiable_id }}</td>
                            <td>{{ __('aqdar.notifications.channels.'.$row->channel?->value) }}</td>
                            <td class="en">{{ $row->attempts }}</td>
                            <td class="en">{{ $row->provider_response ?? $row->skip_reason ?? '—' }}</td>
                            <td>
                                <span class="badge {{ $row->status?->value === 'sent' ? 'ok' : ($row->status?->value === 'failed' ? 'warn' : 'grey') }}">{{ __('aqdar.notifications.delivery.'.$row->status?->value) }}</span>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7">{{ __('aqdar.notifications.empty_log') }}</td></tr>
                    @endforelse
                </table>
            </div>
            {{ $deliveries->links() }}
        </div>
    @endif
@endsection
