import { AppQr } from "@/components/qr/AppQr";
import { StoreButtons } from "@/components/store/StoreButtons";
import { Container } from "@/components/layout/Container";
import type { Dictionary, Locale } from "@/lib/i18n";
import { localePath } from "@/lib/i18n";

type DownloadProps = {
  locale: Locale;
  dict: Dictionary;
};

export function Download({ locale, dict }: DownloadProps) {
  return (
    <section id="download" className="scroll-mt-24 bg-cream-2 py-20">
      <Container>
        <div className="grid items-center gap-10 overflow-hidden rounded-[2rem] bg-navy px-6 py-10 text-white sm:px-10 lg:grid-cols-[1.1fr_0.9fr]">
          <div>
            <p className="text-xs font-semibold uppercase tracking-[0.28em] text-gold">{dict.download.kicker}</p>
            <h2 className="mt-3 text-3xl font-extrabold sm:text-5xl">{dict.download.title}</h2>
            <p className="mt-4 max-w-xl text-base leading-8 text-white/70">{dict.download.lead}</p>
            <p className="mt-6 text-sm text-white/55">{dict.download.storesHint}</p>
            <div className="mt-6">
              <StoreButtons
                overline={dict.download.storeOverline}
                iosLabel={dict.download.ios}
                androidLabel={dict.download.android}
                getHref={localePath(locale, "get")}
                light
              />
            </div>
          </div>
          <div className="mx-auto w-full max-w-[260px] rounded-[1.8rem] bg-white p-5 text-navy">
            <AppQr locale={locale} label={dict.download.qrLabel} />
            <p className="mt-3 text-center text-sm font-semibold">{dict.download.qrCaption}</p>
          </div>
        </div>
      </Container>
    </section>
  );
}
