@props(['action', 'active', 'locked' => false])

@if ($locked)
    <span class="badge gold">{{ __('aqdar.catalog.other_option') }}</span>
@else
    <form method="POST" action="{{ $action }}" style="display:inline">
        @csrf
        @method('PUT')
        <input type="hidden" name="is_active" value="{{ $active ? '0' : '1' }}">
        <button class="btn sm {{ $active ? 'ghost' : '' }}" type="submit">
            {{ $active ? __('aqdar.catalog.deactivate') : __('aqdar.catalog.activate') }}
        </button>
    </form>
@endif
