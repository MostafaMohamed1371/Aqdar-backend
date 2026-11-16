import type { Metadata } from "next";
import { Cairo, Inter } from "next/font/google";
import { notFound } from "next/navigation";
import { Footer } from "@/components/layout/Footer";
import { Header } from "@/components/layout/Header";
import { ScrollToTop } from "@/components/layout/ScrollToTop";
import { getDictionary, isLocale, localeDir, locales, type Locale } from "@/lib/i18n";
import { site } from "@/lib/site";

const cairo = Cairo({
  subsets: ["arabic", "latin"],
  variable: "--font-cairo",
  display: "swap",
});

const inter = Inter({
  subsets: ["latin"],
  variable: "--font-inter",
  display: "swap",
});

export const instant = false;

export function generateStaticParams() {
  return locales.map((locale) => ({ locale }));
}

export async function generateMetadata({
  params,
}: {
  params: Promise<{ locale: string }>;
}): Promise<Metadata> {
  const { locale } = await params;
  const active: Locale = isLocale(locale) ? locale : "ar";
  const dict = getDictionary(active);
  const path = `/${active}`;

  return {
    metadataBase: new URL(site.url),
    title: dict.meta.title,
    description: dict.meta.description,
    alternates: {
      canonical: path,
      languages: {
        ar: "/ar",
        en: "/en",
      },
    },
    openGraph: {
      title: dict.meta.title,
      description: dict.meta.description,
      locale: active === "ar" ? "ar_EG" : "en_US",
      type: "website",
      url: path,
      siteName: "AQDAR",
    },
    twitter: {
      card: "summary_large_image",
      title: dict.meta.title,
      description: dict.meta.description,
    },
    icons: {
      icon: "/favicon.svg",
    },
  };
}

export default async function LocaleLayout({
  children,
  params,
}: {
  children: React.ReactNode;
  params: Promise<{ locale: string }>;
}) {
  const { locale } = await params;

  if (!isLocale(locale)) {
    notFound();
  }

  const dict = getDictionary(locale);
  const dir = localeDir(locale);

  return (
    <html
      lang={locale}
      dir={dir}
      data-scroll-behavior="smooth"
      className={`${cairo.variable} ${inter.variable} h-full antialiased`}
    >
      <body className="min-h-full bg-cream-2 text-ink">
        <Header locale={locale} dict={dict} />
        {children}
        <Footer locale={locale} dict={dict} />
        <ScrollToTop label={dict.scrollTop} />
        <script
          type="application/ld+json"
          dangerouslySetInnerHTML={{
            __html: JSON.stringify({
              "@context": "https://schema.org",
              "@type": "MobileApplication",
              name: "AQDAR",
              alternateName: "عَقدار",
              applicationCategory: "RealEstateApplication",
              operatingSystem: "iOS, Android",
              inLanguage: ["ar", "en"],
              description: dict.meta.description,
              offers: {
                "@type": "Offer",
                price: "0",
                priceCurrency: "EGP",
              },
            }),
          }}
        />
      </body>
    </html>
  );
}
