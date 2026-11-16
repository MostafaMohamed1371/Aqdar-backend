import { Container } from "@/components/layout/Container";
import { SectionHeading } from "@/components/home/SectionHeading";
import type { Dictionary } from "@/lib/i18n";

export function Advantages({ dict }: { dict: Dictionary }) {
  return (
    <section id="advantages" className="scroll-mt-24 bg-cream-2 py-20">
      <Container>
        <SectionHeading
          kicker={dict.advantages.kicker}
          title={dict.advantages.title}
          lead={dict.advantages.lead}
        />
        <div className="mt-12 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
          {dict.advantages.items.map((item, index) => (
            <article
              key={item.title}
              className={`rounded-[1.5rem] border border-line p-6 ${
                index === 0 ? "bg-navy text-white sm:col-span-2" : "bg-white"
              }`}
            >
              <span
                className={`mb-4 inline-flex h-9 w-9 items-center justify-center rounded-full text-xs font-bold ${
                  index === 0 ? "bg-teal text-white" : "bg-teal-soft text-teal"
                }`}
              >
                {index + 1}
              </span>
              <h3 className={`text-lg font-extrabold ${index === 0 ? "text-white" : "text-navy"}`}>
                {item.title}
              </h3>
              <p className={`mt-2 text-sm leading-7 ${index === 0 ? "text-white/70" : "text-muted"}`}>
                {item.body}
              </p>
            </article>
          ))}
        </div>
      </Container>
    </section>
  );
}
