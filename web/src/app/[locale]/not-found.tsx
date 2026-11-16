import Link from "next/link";
import { Container } from "@/components/layout/Container";

export default function LocaleNotFound() {
  return (
    <main id="main" className="bg-cream-2 py-24">
      <Container className="max-w-lg text-center">
        <p className="text-xs font-semibold tracking-[0.28em] text-teal uppercase">AQDAR</p>
        <h1 className="mt-4 text-3xl font-extrabold text-navy">هذه الصفحة غير موجودة</h1>
        <p className="mt-2 text-muted">This page is not here.</p>
        <Link
          href="/ar"
          className="mt-8 inline-flex rounded-full bg-teal px-5 py-2.5 text-sm font-semibold text-white"
        >
          عَقدار
        </Link>
      </Container>
    </main>
  );
}
