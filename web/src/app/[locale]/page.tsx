import { Advantages } from "@/components/home/Advantages";
import { CityStrip } from "@/components/home/CityStrip";
import { Download } from "@/components/home/Download";
import { Hero } from "@/components/home/Hero";
import { Journey } from "@/components/home/Journey";
import { Markets } from "@/components/home/Markets";
import { Trust } from "@/components/home/Trust";
import { WhatIs } from "@/components/home/WhatIs";
import { getDictionary, isLocale } from "@/lib/i18n";
import { notFound } from "next/navigation";

export const instant = false;

export default async function HomePage({
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
    <main id="main">
      <Hero locale={locale} dict={dict} />
      <CityStrip dict={dict} />
      <WhatIs dict={dict} />
      <Markets dict={dict} />
      <Advantages dict={dict} />
      <Journey dict={dict} />
      <Trust dict={dict} />
      <Download locale={locale} dict={dict} />
    </main>
  );
}
