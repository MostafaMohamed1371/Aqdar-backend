"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";
import { useState } from "react";
import { Logo } from "@/components/brand/Logo";
import { Container } from "@/components/layout/Container";
import type { Dictionary, Locale } from "@/lib/i18n";
import { localePath } from "@/lib/i18n";

type HeaderProps = {
  locale: Locale;
  dict: Dictionary;
};

export function Header({ locale, dict }: HeaderProps) {
  const [open, setOpen] = useState(false);
  const pathname = usePathname();
  const otherLocale: Locale = locale === "ar" ? "en" : "ar";
  const rest = pathname.replace(/^\/(ar|en)/, "") || "";
  const home = localePath(locale);
  const links = [
    { href: `${home}#what`, label: dict.nav.what },
    { href: `${home}#advantages`, label: dict.nav.advantages },
    { href: `${home}#journey`, label: dict.nav.journey },
    { href: `${home}#download`, label: dict.nav.download },
  ];

  return (
    <header className="sticky top-0 z-50 border-b border-white/10 bg-navy-deep/80 backdrop-blur-xl">
      <a className="skip-link" href="#main">
        {dict.nav.skip}
      </a>
      <Container className="flex h-[4.25rem] items-center justify-between gap-4">
        <Link href={home} className="shrink-0" aria-label={locale === "ar" ? "عَقدار" : "AQDAR"}>
          <Logo locale={locale} variant="light" size="sm" />
        </Link>
        <nav className="hidden items-center gap-1 lg:flex" aria-label="Primary">
          {links.map((link) => (
            <Link
              key={link.href}
              href={link.href}
              className="rounded-full px-3.5 py-2 text-sm text-white/75 transition hover:bg-white/10 hover:text-white"
            >
              {link.label}
            </Link>
          ))}
        </nav>
        <div className="flex items-center gap-2">
          <a
            href={`/${otherLocale}${rest}`}
            className="hidden rounded-full border border-white/15 px-3 py-1.5 text-xs font-semibold tracking-wide text-white/80 hover:border-gold hover:text-gold sm:inline-flex"
            hrefLang={otherLocale}
            aria-label={dict.nav.languageAria}
          >
            {dict.nav.language}
          </a>
          <Link
            href={`${home}#download`}
            className="hidden rounded-full bg-teal px-4 py-2 text-sm font-semibold text-white shadow-[0_8px_20px_rgba(8,126,130,0.35)] transition hover:bg-teal-2 sm:inline-flex"
          >
            {dict.nav.download}
          </Link>
          <button
            type="button"
            className="inline-flex h-10 w-10 items-center justify-center rounded-full border border-white/15 text-white lg:hidden"
            aria-expanded={open}
            aria-controls="mobile-nav"
            onClick={() => setOpen((value) => !value)}
          >
            <span className="sr-only">{open ? dict.nav.close : dict.nav.menu}</span>
            <span className="flex flex-col gap-1.5">
              <span className={`block h-0.5 w-4 bg-current transition ${open ? "translate-y-2 rotate-45" : ""}`} />
              <span className={`block h-0.5 w-4 bg-current transition ${open ? "opacity-0" : ""}`} />
              <span className={`block h-0.5 w-4 bg-current transition ${open ? "-translate-y-2 -rotate-45" : ""}`} />
            </span>
          </button>
        </div>
      </Container>
      {open ? (
        <div id="mobile-nav" className="border-t border-white/10 bg-navy-deep lg:hidden">
          <Container className="flex flex-col gap-1 py-4">
            {links.map((link) => (
              <Link
                key={link.href}
                href={link.href}
                className="rounded-xl px-3 py-3 text-white/90"
                onClick={() => setOpen(false)}
              >
                {link.label}
              </Link>
            ))}
            <a
              href={`/${otherLocale}${rest}`}
              className="rounded-xl px-3 py-3 text-gold"
              hrefLang={otherLocale}
              onClick={() => setOpen(false)}
            >
              {dict.nav.language}
            </a>
          </Container>
        </div>
      ) : null}
    </header>
  );
}
