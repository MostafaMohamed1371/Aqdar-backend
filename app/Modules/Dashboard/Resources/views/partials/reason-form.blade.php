@props(['action', 'label', 'tone' => ''])
<form method="POST" action="{{ $action }}" style="display:flex;gap:8px;align-items:flex-start;flex-wrap:wrap;margin:6px 0">
    @csrf
    <input type="text" name="reason" required minlength="3" maxlength="500" placeholder="{{ __('aqdar.listings.desk.reason') }}" style="flex:1;min-width:180px">
    <button type="submit" class="btn sm {{ $tone }}">{{ $label }}</button>
</form>
