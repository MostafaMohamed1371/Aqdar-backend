export function SectionHeading({
  kicker,
  title,
  lead,
  invert = false,
}: {
  kicker: string;
  title: string;
  lead?: string;
  invert?: boolean;
}) {
  return (
    <div className="mx-auto max-w-3xl text-center">
      <p
        className={`text-xs font-semibold uppercase tracking-[0.28em] ${invert ? "text-gold" : "text-teal"}`}
      >
        {kicker}
      </p>
      <h2
        className={`mt-3 text-3xl font-extrabold leading-tight tracking-tight sm:text-4xl ${invert ? "text-white" : "text-navy"}`}
      >
        {title}
      </h2>
      {lead ? (
        <p className={`mt-4 text-base leading-8 sm:text-lg ${invert ? "text-white/70" : "text-muted"}`}>
          {lead}
        </p>
      ) : null}
    </div>
  );
}
