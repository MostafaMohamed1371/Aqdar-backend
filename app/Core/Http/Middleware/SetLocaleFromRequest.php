<?php

namespace App\Core\Http\Middleware;

use Closure;
use CodeZero\BrowserLocale\BrowserLocale;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocaleFromRequest
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $this->resolveLocale($request);

        if ($locale !== null) {
            app()->setLocale($locale);

            if ($request->hasSession()) {
                $request->session()->put('locale', $locale);
            }
        }

        /** @var Response $response */
        $response = $next($request);

        $response->headers->set('Content-Language', app()->getLocale());

        return $response;
    }

    protected function resolveLocale(Request $request): ?string
    {
        $available = config('structer.available_locales', ['en']);

        if ($request->hasSession() && $request->session()->has('locale')) {
            $sessionLocale = $request->session()->get('locale');

            if (is_string($sessionLocale) && in_array($sessionLocale, $available, true)) {
                return $sessionLocale;
            }
        }

        $queryLocale = $request->query('lang');

        if (is_string($queryLocale) && in_array($queryLocale, $available, true)) {
            return $queryLocale;
        }

        $headerLocale = $this->localeFromAcceptLanguage(
            $request->header('Accept-Language', ''),
            $available,
        );

        if ($headerLocale !== null) {
            return $headerLocale;
        }

        $default = config('app.locale', 'en');

        return in_array($default, $available, true) ? $default : $available[0] ?? 'en';
    }

    /** @param  list<string>  $available */
    protected function localeFromAcceptLanguage(string $acceptLanguage, array $available): ?string
    {
        if ($acceptLanguage === '') {
            return null;
        }

        $browser = new BrowserLocale($acceptLanguage);

        foreach ($browser->getLocales() as $locale) {
            $code = strtolower($locale->language);

            if (in_array($code, $available, true)) {
                return $code;
            }

            $full = strtolower(str_replace('_', '-', $locale->locale));
            $normalized = explode('-', $full)[0];

            if (in_array($normalized, $available, true)) {
                return $normalized;
            }
        }

        return null;
    }
}
