@if (request('sort'))<input type="hidden" name="sort" value="{{ request('sort') }}">@endif
@if (request('dir'))<input type="hidden" name="dir" value="{{ request('dir') }}">@endif
<label class="tiny" style="display:flex;align-items:center;gap:4px">{{ __('aqdar.queue.from') }}
    <input type="date" name="from" value="{{ request('from') }}" aria-label="{{ __('aqdar.queue.from') }}">
</label>
<label class="tiny" style="display:flex;align-items:center;gap:4px">{{ __('aqdar.queue.to') }}
    <input type="date" name="to" value="{{ request('to') }}" aria-label="{{ __('aqdar.queue.to') }}">
</label>
