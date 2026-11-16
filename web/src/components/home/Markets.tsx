import { Container } from "@/components/layout/Container";
import { SectionHeading } from "@/components/home/SectionHeading";
import type { Dictionary } from "@/lib/i18n";

export function Markets({ dict }: { dict: Dictionary }) {
  return (
    <section className="bg-cream py-20">
      <Container>
        <SectionHeading kicker={dict.markets.kicker} title={dict.markets.title} lead={dict.markets.lead} />
        <div className="mt-12 grid gap-6 lg:grid-cols-2">
          {dict.markets.items.map((market, index) => (
            <article
              key={market.title}
              className={`relative overflow-hidden rounded-[1.8rem] p-8 sm:p-10 ${
                index === 0 ? "bg-navy text-white" : "bg-white text-navy ring-1 ring-line"
              }`}
            >
              <p
                className={`text-xs font-semibold uppercase tracking-[0.22em] ${index === 0 ? "text-gold" : "text-teal"}`}
              >
                {market.tag}
              </p>
              <h3 className="mt-4 text-3xl font-extrabold">{market.title}</h3>
              <p className={`mt-4 max-w-md text-sm leading-7 ${index === 0 ? "text-white/70" : "text-muted"}`}>
                {market.body}
              </p>
              <div
                className={`pointer-events-none absolute -end-8 -bottom-10 h-40 w-40 rounded-full ${
                  index === 0 ? "bg-teal/30" : "bg-gold/20"
                }`}
              />
            </article>
          ))}
        </div>
      </Container>
    </section>
  );
}
