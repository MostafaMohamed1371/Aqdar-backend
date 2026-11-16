import type { Metadata } from "next";
import { Container } from "@/components/layout/Container";
import { getDictionary, isLocale } from "@/lib/i18n";
import { notFound } from "next/navigation";

export const instant = false;

export async function generateMetadata({
  params,
}: {
  params: Promise<{ locale: string }>;
}): Promise<Metadata> {
  const { locale } = await params;
  const dict = getDictionary(isLocale(locale) ? locale : "ar");

  return {
    title: `${dict.privacy.title} | AQDAR`,
    description: dict.privacy.lead,
  };
}

export default async function PrivacyPage({
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
      <Container className="max-w-3xl">
        <h1 className="text-4xl font-extrabold text-navy">{dict.privacy.title}</h1>
        <p className="mt-4 text-lg leading-8 text-muted">{dict.privacy.lead}</p>
        <div className="mt-10 space-y-4">
          {dict.privacy.items.map((item) => (
            <article key={item.title} className="rounded-[1.4rem] bg-white p-6 ring-1 ring-line">
              <h2 className="text-xl font-extrabold text-navy">{item.title}</h2>
              <p className="mt-2 text-sm leading-7 text-muted">{item.body}</p>
            </article>
          ))}
        </div>
      </Container>
    </main>
  );
}
