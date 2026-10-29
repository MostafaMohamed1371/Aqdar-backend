<?php

namespace App\Core\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LocaleController extends BaseController
{
    public function switch(Request $request, string $locale): RedirectResponse
    {
        $available = config('structer.available_locales', ['en']);

        if (! in_array($locale, $available, true)) {
            abort(404);
        }

        $request->session()->put('locale', $locale);
        app()->setLocale($locale);

        return redirect()->back();
    }
}
