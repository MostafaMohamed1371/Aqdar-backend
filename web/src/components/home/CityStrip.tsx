import type { Dictionary } from "@/lib/i18n";

export function CityStrip({ dict }: { dict: Dictionary }) {
  const items = [...dict.cities.items, ...dict.cities.items];

  return (
    <section className="border-y border-line bg-cream overflow-hidden py-5" aria-label={dict.cities.label}>
      <p className="sr-only">{dict.cities.label}</p>
      <div className="marquee-track flex w-max gap-10 px-6 text-sm font-semibold tracking-wide text-navy/70">
        {items.map((city, index) => (
          <span key={`${city}-${index}`} className="flex items-center gap-10">
            {city}
            <span className="inline-block h-1.5 w-1.5 rounded-full bg-gold" />
          </span>
        ))}
      </div>
    </section>
  );
}
