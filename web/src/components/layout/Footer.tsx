import Link from "next/link";
import { Logo } from "@/components/brand/Logo";
import { Container } from "@/components/layout/Container";
import type { Dictionary, Locale } from "@/lib/i18n";
import { localePath } from "@/lib/i18n";
import { salesHours, salesTelHref, salesWhatsAppHref, site } from "@/lib/site";

type FooterProps = {
  locale: Locale;
  dict: Dictionary;
};

export function Footer({ locale, dict }: FooterProps) {
  const home = localePath(locale);
  const hours = salesHours(locale);

  return (
    <footer className="border-t border-white/10 bg-navy-deep text-white">
      <Container className="grid gap-10 py-14 sm:grid-cols-2 lg:grid-cols-4">
        <div className="sm:col-span-2">
          <Logo locale={locale} variant="light" />
          <p className="mt-4 max-w-md text-sm leading-7 text-white/65">{dict.footer.blurb}</p>
        </div>
        <div>
          <p className="text-xs font-semibold uppercase tracking-[0.2em] text-gold">{dict.footer.product}</p>
          <ul className="mt-4 space-y-2 text-sm text-white/75">
            <li>
              <Link href={`${home}#what`} className="hover:text-white">
                {dict.nav.what}
              </Link>
            </li>
            <li>
              <Link href={`${home}#advantages`} className="hover:text-white">
                {dict.nav.advantages}
              </Link>
            </li>
            <li>
              <Link href={`${home}#journey`} className="hover:text-white">
                {dict.nav.journey}
              </Link>
            </li>
            <li>
              <Link href={`${home}#download`} className="hover:text-white">
                {dict.nav.download}
              </Link>
            </li>
          </ul>
        </div>
        <div>
          <p className="text-xs font-semibold uppercase tracking-[0.2em] text-gold">{dict.footer.contact}</p>
          <div className="mt-4 space-y-3 text-sm">
            <div>
              <p className="font-semibold text-white">{dict.footer.sales}</p>
              <p className="mt-1 text-white/60">{dict.footer.salesLead}</p>
            </div>
            <a
              href={salesTelHref()}
              className="block font-semibold text-teal-2 hover:text-white"
              dir="ltr"
            >
              {site.salesPhone}
            </a>
            <p className="text-white/55">{hours}</p>
            <div className="flex flex-wrap gap-2 pt-1">
              <a
                href={salesTelHref()}
                className="inline-flex items-center rounded-full bg-teal px-3.5 py-1.5 text-xs font-semibold text-white hover:bg-teal-2"
              >
                {dict.footer.call}
              </a>
              <a
                href={salesWhatsAppHref()}
                target="_blank"
                rel="noopener noreferrer"
                className="inline-flex items-center rounded-full border border-white/20 px-3.5 py-1.5 text-xs font-semibold text-white/90 hover:border-white/50"
              >
                {dict.footer.whatsapp}
              </a>
            </div>
            <ul className="space-y-2 pt-2 text-white/75">
              <li>
                <Link href={localePath(locale, "privacy")} className="hover:text-white">
                  {dict.nav.privacy}
                </Link>
              </li>
            </ul>
          </div>
        </div>
      </Container>
      <div className="border-t border-white/10">
        <Container className="flex flex-wrap items-center justify-between gap-3 py-5 text-xs text-white/45">
          <p>© 2026 {dict.footer.rights}</p>
          <p className="latin tracking-[0.2em] uppercase">EGYPT · EGP</p>
        </Container>
      </div>
    </footer>
  );
}
