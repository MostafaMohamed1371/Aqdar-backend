import Link from "next/link";
import { AppQr } from "@/components/qr/AppQr";
import { Container } from "@/components/layout/Container";
import { PhoneMock } from "@/components/home/PhoneMock";
import type { Dictionary, Locale } from "@/lib/i18n";
import { localePath } from "@/lib/i18n";

type HeroProps = {
  locale: Locale;
  dict: Dictionary;
};

export function Hero({ locale, dict }: HeroProps) {
  const home = localePath(locale);

  return (
    <section className="relative overflow-hidden bg-navy-deep text-white grain">
      <div className="surface-grid absolute inset-0" />
      <Container className="relative grid items-center gap-12 py-16 lg:grid-cols-[1.05fr_0.95fr] lg:py-24">
        <div>
          <p className="inline-flex rounded-full border border-gold/40 bg-gold/10 px-3 py-1 text-xs font-semibold tracking-[0.22em] text-gold uppercase">
            {dict.hero.kicker}
          </p>
          <h1 className="mt-6 max-w-xl text-4xl font-extrabold leading-[1.15] tracking-tight sm:text-6xl">
            {dict.hero.title}
          </h1>
          <p className="mt-5 max-w-xl text-lg leading-8 text-white/72">{dict.hero.lead}</p>
          <div className="mt-8 flex flex-wrap items-center gap-3">
            <Link
              href={`${home}#download`}
              className="rounded-full bg-teal px-6 py-3 text-sm font-semibold text-white shadow-[0_12px_30px_rgba(8,126,130,0.35)] transition hover:bg-teal-2"
            >
              {dict.hero.primary}
            </Link>
            <Link
              href={`${home}#what`}
              className="rounded-full border border-white/20 px-6 py-3 text-sm font-semibold text-white/90 hover:border-white/50"
            >
              {dict.hero.secondary}
            </Link>
          </div>
          <ul className="mt-10 grid grid-cols-2 gap-3 text-sm text-white/70 sm:grid-cols-4">
            {dict.hero.trust.map((item) => (
              <li key={item} className="rounded-2xl border border-white/10 bg-white/5 px-3 py-3">
                {item}
              </li>
            ))}
          </ul>
        </div>
        <div className="relative flex items-end justify-center gap-5">
          <PhoneMock dict={dict} />
          <div className="absolute -bottom-2 end-0 hidden w-40 rounded-3xl border border-white/10 bg-white p-3 text-navy shadow-[0_20px_50px_rgba(0,0,0,0.28)] sm:block lg:-end-4">
            <AppQr locale={locale} label={dict.download.qrLabel} />
            <p className="mt-2 text-center text-[11px] font-semibold text-muted">{dict.hero.qrHint}</p>
          </div>
        </div>
      </Container>
    </section>
  );
}
