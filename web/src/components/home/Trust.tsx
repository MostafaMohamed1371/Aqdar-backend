import { Container } from "@/components/layout/Container";
import { SectionHeading } from "@/components/home/SectionHeading";
import type { Dictionary } from "@/lib/i18n";

export function Trust({ dict }: { dict: Dictionary }) {
  return (
    <section className="bg-cream py-20">
      <Container>
        <SectionHeading kicker={dict.trust.kicker} title={dict.trust.title} lead={dict.trust.lead} />
        <div className="mt-12 grid gap-4 md:grid-cols-2">
          {dict.trust.items.map((item) => (
            <article key={item.title} className="flex gap-4 rounded-[1.5rem] bg-white p-6 ring-1 ring-line">
              <span className="mt-1 h-2.5 w-2.5 shrink-0 rounded-full bg-gold" />
              <div>
                <h3 className="text-lg font-extrabold text-navy">{item.title}</h3>
                <p className="mt-2 text-sm leading-7 text-muted">{item.body}</p>
              </div>
            </article>
          ))}
        </div>
      </Container>
    </section>
  );
}
