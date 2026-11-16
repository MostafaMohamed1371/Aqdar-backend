import { Container } from "@/components/layout/Container";
import { SectionHeading } from "@/components/home/SectionHeading";
import type { Dictionary } from "@/lib/i18n";

export function WhatIs({ dict }: { dict: Dictionary }) {
  return (
    <section id="what" className="scroll-mt-24 bg-cream-2 py-20">
      <Container>
        <SectionHeading kicker={dict.what.kicker} title={dict.what.title} lead={dict.what.lead} />
        <div className="mt-12 grid gap-5 md:grid-cols-3">
          {dict.what.pillars.map((pillar, index) => (
            <article
              key={pillar.title}
              className="rounded-[1.6rem] border border-line bg-white p-7 shadow-[0_10px_30px_rgba(20,40,63,0.04)]"
            >
              <span className="text-xs font-bold tracking-[0.2em] text-gold">0{index + 1}</span>
              <h3 className="mt-3 text-2xl font-extrabold text-navy">{pillar.title}</h3>
              <p className="mt-3 text-sm leading-7 text-muted">{pillar.body}</p>
            </article>
          ))}
        </div>
        <p className="mx-auto mt-10 max-w-3xl rounded-2xl bg-teal-soft px-5 py-4 text-center text-sm leading-7 text-navy">
          {dict.what.note}
        </p>
      </Container>
    </section>
  );
}
