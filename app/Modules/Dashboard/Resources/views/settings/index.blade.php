@extends('dashboard::layouts.app')

@section('title', __('aqdar.menu.adm_13'))
@section('screen', 'ADM-13')

@section('content')
    @php
        $tab = $tab ?? 'general';
    @endphp
    <form method="POST" action="{{ route('dashboard.settings.update') }}">
        @csrf
        @method('PUT')
        <input type="hidden" name="tab" value="{{ $tab }}">
        <div class="ptitle">
            <h2><x-bilingual key="aqdar.menu.adm_13" /></h2>
            <div class="acts">
                <button type="submit" class="btn">{{ __('aqdar.settings.save') }}</button>
            </div>
        </div>

        <div class="ctabs">
            <a href="{{ route('dashboard.settings.index', ['tab' => 'general']) }}" @class(['on' => $tab === 'general'])>{{ __('aqdar.settings.tab_general') }}</a>
            <a href="{{ route('dashboard.settings.index', ['tab' => 'security']) }}" @class(['on' => $tab === 'security'])>{{ __('aqdar.settings.tab_security') }}</a>
            <a href="{{ route('dashboard.settings.index', ['tab' => 'audit']) }}" @class(['on' => $tab === 'audit'])>{{ __('aqdar.settings.tab_audit') }}</a>
        </div>

        <div class="stab @if ($tab === 'general') on @endif" data-settings-panel="general">
            <div class="cols2e">
                <div>
                    <div class="card">
                        <div class="card-h"><b>{{ __('aqdar.settings.general') }}</b></div>
                        <x-form-input name="platform_name" :label="__('aqdar.settings.platform_name')" :value="old('platform_name', $form['platform_name'])" />
                        <x-form-input name="support_email" type="email" :label="__('aqdar.settings.support_email')" :value="old('support_email', $form['support_email'])" />
                        <div class="field">
                            <label for="default_locale">{{ __('aqdar.settings.default_locale') }}</label>
                            <div class="inp">
                                <select id="default_locale" name="default_locale">
                                    <option value="ar" @selected(old('default_locale', $form['default_locale']) === 'ar')>{{ __('aqdar.settings.locale_ar') }}</option>
                                    <option value="en" @selected(old('default_locale', $form['default_locale']) === 'en')>{{ __('aqdar.settings.locale_en') }}</option>
                                </select>
                            </div>
                        </div>
                        <div class="field">
                            <label>{{ __('aqdar.settings.currency') }}</label>
                            <div class="inp"><input class="en" value="EGP" disabled></div>
                        </div>
                        <div class="dl">
                            <span>{{ __('aqdar.settings.maintenance') }}</span>
                            <label class="sw-toggle">
                                <input type="hidden" name="maintenance_mode" value="0">
                                <input type="checkbox" name="maintenance_mode" value="1" @checked(old('maintenance_mode', $form['maintenance_mode']) === '1')>
                                <span class="sw"></span>
                            </label>
                        </div>
                    </div>
                    <div class="card">
                        <div class="card-h"><b>@include('dashboard::partials.icon', ['name' => 'phone']) {{ __('aqdar.settings.sales_title') }}</b><span class="tiny">{{ __('aqdar.settings.sales_hint') }}</span></div>
                        <x-form-input name="sales_name" :label="__('aqdar.settings.sales_name')" :value="old('sales_name', $form['sales_name'])" />
                        <x-form-input name="sales_name_en" :label="__('aqdar.settings.sales_name_en')" :value="old('sales_name_en', $form['sales_name_en'])" />
                        <x-form-input name="sales_phone" :label="__('aqdar.settings.sales_phone')" :value="old('sales_phone', $form['sales_phone'])" />
                        <x-form-input name="sales_hours" :label="__('aqdar.settings.sales_hours')" :value="old('sales_hours', $form['sales_hours'])" />
                        <x-form-input name="sales_hours_en" :label="__('aqdar.settings.sales_hours_en')" :value="old('sales_hours_en', $form['sales_hours_en'])" />
                        <div class="field">
                            <label for="lead_assignment_rule">{{ __('aqdar.settings.assignment_rule') }}</label>
                            <div class="inp">
                                <select id="lead_assignment_rule" name="lead_assignment_rule">
                                    <option value="manual" @selected(old('lead_assignment_rule', $form['lead_assignment_rule']) === 'manual')>{{ __('aqdar.settings.assignment_manual') }}</option>
                                    <option value="round_robin" @selected(old('lead_assignment_rule', $form['lead_assignment_rule']) === 'round_robin')>{{ __('aqdar.settings.assignment_round_robin') }}</option>
                                </select>
                            </div>
                        </div>
                        <p class="tiny">
                            @if ($lastChange)
                                {{ __('aqdar.settings.last_change', [
                                    'staff' => $lastChange->actor?->name ?? $lastChange->actor_type,
                                    'date' => optional($lastChange->created_at)?->format('Y/m/d H:i'),
                                ]) }}
                            @else
                                {{ __('aqdar.settings.last_change_never') }}
                            @endif
                            · {{ __('aqdar.settings.applies_immediately') }}
                        </p>
                    </div>
                </div>
                <div>
                    <div class="card">
                        <div class="card-h"><b>{{ __('aqdar.settings.flags') }}</b></div>
                        <div class="dl">
                            <span>{{ __('aqdar.settings.smart_notifications') }}</span>
                            <label class="sw-toggle">
                                <input type="hidden" name="feature_smart_notifications" value="0">
                                <input type="checkbox" name="feature_smart_notifications" value="1" @checked(old('feature_smart_notifications', $form['feature_smart_notifications']) === '1')>
                                <span class="sw"></span>
                            </label>
                        </div>
                        <div class="dl"><span>{{ __('aqdar.settings.providers') }}</span><b>{{ __('aqdar.settings.provider_ok') }} · SMS {{ $providerStatuses['sms'] }} / Push {{ $providerStatuses['push'] }} / {{ __('aqdar.settings.storage') }} {{ $providerStatuses['storage'] }}</b></div>
                        <div class="dl"><span>{{ __('aqdar.settings.buyer_seller_chat') }}</span><b style="color:var(--bad)">{{ __('aqdar.settings.chat_forbidden') }}</b></div>
                        <div class="dl"><span>{{ __('aqdar.settings.support_chat') }}</span><b style="color:var(--ok)">{{ __('aqdar.settings.support_chat_on') }}</b></div>
                        <x-form-input name="notification_quiet_hours" :label="__('aqdar.settings.quiet_hours')" :value="old('notification_quiet_hours', $form['notification_quiet_hours'])" placeholder="22:00-08:00" />
                    </div>
                    <div class="card">
                        <div class="card-h"><b>{{ __('aqdar.settings.business') }}</b></div>
                        <div class="dl"><span>{{ __('aqdar.settings.publish_after_review') }}</span><span class="sw on"></span></div>
                        <div class="dl"><span>{{ __('aqdar.settings.guest_browse') }}</span><span class="sw on"></span></div>
                        <x-form-input name="commission_default_rate" :label="__('aqdar.settings.default_commission')" :value="old('commission_default_rate', $form['commission_default_rate'])" />
                    </div>
                </div>
            </div>
        </div>

        <div class="stab @if ($tab === 'security') on @endif" data-settings-panel="security">
            <div class="card">
                <div class="card-h"><b>{{ __('aqdar.settings.security') }}</b></div>
                <div class="dl">
                    <span>{{ __('aqdar.settings.two_factor') }}</span>
                    <span style="display:flex;align-items:center;gap:8px">
                        <label class="sw-toggle"><input type="checkbox" disabled><span class="sw"></span></label>
                        <b>{{ __('aqdar.settings.not_enabled') }}</b>
                    </span>
                </div>
                <div class="dl">
                    <span>{{ __('aqdar.settings.ip_verify') }}</span>
                    <span style="display:flex;align-items:center;gap:8px">
                        <label class="sw-toggle"><input type="checkbox" disabled><span class="sw"></span></label>
                        <b>{{ __('aqdar.settings.not_enabled') }}</b>
                    </span>
                </div>
                <div class="dl"><span>{{ __('aqdar.settings.audit_all') }}</span><span class="sw on"></span></div>
                <div class="dl"><span>{{ __('aqdar.settings.signed_url') }}</span><span class="sw on"></span></div>
                <div class="dl"><span>{{ __('aqdar.settings.watermark') }}</span><span class="sw on"></span></div>
                <div class="dl"><span>{{ __('aqdar.settings.mask_phones') }}</span><span class="sw on"></span></div>
                <div class="dl"><span>{{ __('aqdar.settings.leak_scan') }}</span><span class="sw on"></span></div>
                <div class="dl"><span>{{ __('aqdar.settings.provider_sms') }}</span><b>{{ $providerStatuses['sms'] }}</b></div>
                <div class="dl"><span>{{ __('aqdar.settings.provider_push') }}</span><b>{{ $providerStatuses['push'] }}</b></div>
                <div class="dl"><span>{{ __('aqdar.settings.provider_storage') }}</span><b>{{ $providerStatuses['storage'] }}</b></div>
            </div>
        </div>

        <div class="stab @if ($tab === 'audit') on @endif" data-settings-panel="audit">
            <div class="card">
                <div class="card-h"><b>{{ __('aqdar.settings.tab_audit') }}</b></div>
                <x-form-input name="audit_retention_months" type="number" :label="__('aqdar.settings.retention')" :value="old('audit_retention_months', $form['audit_retention_months'])" />
                <p class="tiny">
                    @if ($lastChange)
                        {{ __('aqdar.settings.last_change', [
                            'staff' => $lastChange->actor?->name ?? $lastChange->actor_type,
                            'date' => optional($lastChange->created_at)?->format('Y/m/d H:i'),
                        ]) }}
                    @else
                        {{ __('aqdar.settings.last_change_never') }}
                    @endif
                </p>
                <p><a class="btn ghost" href="{{ route('dashboard.audit.index') }}">{{ __('aqdar.menu.adm_14') }}</a></p>
            </div>
        </div>
    </form>
@endsection
