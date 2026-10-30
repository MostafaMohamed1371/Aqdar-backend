@props(['action', 'current' => null, 'assignable', 'inline' => false, 'style' => ''])
<form method="POST" action="{{ $action }}" @if ($inline) style="display:inline" @endif>
    @csrf
    <input type="hidden" name="reason" value="">
    <select name="assigned_to" style="{{ $style }}" data-current="{{ $current }}" data-prompt="{{ __('aqdar.crm.reassign_reason_prompt') }}"
        onchange="if (this.dataset.current && this.value !== this.dataset.current) { const reason = (window.prompt(this.dataset.prompt) || '').trim(); if (reason.length < 3) { this.value = this.dataset.current; return; } this.form.reason.value = reason; } this.form.submit()">
        <option value="">{{ __('aqdar.crm.unassigned') }}</option>
        @foreach ($assignable as $staffMember)
            <option value="{{ $staffMember->id }}" @selected((int) $current === (int) $staffMember->id)>{{ $staffMember->name }}</option>
        @endforeach
    </select>
</form>
