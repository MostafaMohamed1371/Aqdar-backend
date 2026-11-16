import { AppQr } from "@/components/qr/AppQr";
import { StoreButtons } from "@/components/store/StoreButtons";
import { Container } from "@/components/layout/Container";
import { StoreRedirect } from "@/app/[locale]/get/StoreRedirect";
import { getDictionary, isLocale, localePath } from "@/lib/i18n";
import { notFound } from "next/navigation";

export const instant = false;

export default async function GetAppPage({
  params,
}: {
  params: Promise<{ locale: string }>;
}) {
  const { locale } = await params;

  if (!isLocale(locale)) {
    notFound();
  }

  const dict = getDictionary(locale);

  return (
    <main id="main" className="bg-cream-2 py-16">
      <Container className="max-w-xl text-center">
        <p className="text-xs font-semibold uppercase tracking-[0.28em] text-teal">{dict.download.kicker}</p>
        <h1 className="mt-3 text-3xl font-extrabold text-navy sm:text-4xl">{dict.get.title}</h1>
        <p className="mt-4 text-base leading-8 text-muted">{dict.get.lead}</p>
        <div className="mx-auto mt-8 max-w-[240px] rounded-[1.6rem] bg-white p-4 ring-1 ring-line">
          <AppQr locale={locale} label={dict.download.qrLabel} />
        </div>
        <p className="mt-3 text-sm text-muted">{dict.get.desktop}</p>
        <div className="mt-8 flex justify-center">
          <StoreButtons
            overline={dict.download.storeOverline}
            iosLabel={dict.download.ios}
            androidLabel={dict.download.android}
            getHref={localePath(locale, "get")}
          />
        </div>
        <div className="mt-6">
          <StoreRedirect message={dict.get.redirecting} />
        </div>
      </Container>
    </main>
  );
}
