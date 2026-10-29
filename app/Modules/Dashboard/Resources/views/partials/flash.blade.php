@if (session('status'))
    <div class="alert" style="background:var(--ok-soft);border-color:#b7e0cc;color:var(--ok)">
        <b>{{ session('status') }}</b>
    </div>
@endif

@if ($errors->any())
    <div class="alert">
        <b>{{ $errors->first() }}</b>
        @if ($errors->count() > 1)
            <ul class="bul">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        @endif
    </div>
@endif
