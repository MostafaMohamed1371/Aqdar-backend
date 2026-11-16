import type { Dictionary } from "@/lib/i18n";

export function PhoneMock({ dict }: { dict: Dictionary }) {
  return (
    <div className="phone-shell relative mx-auto overflow-hidden">
      <div className="phone-notch" />
      <div className="relative min-h-[560px] bg-cream-2 px-3 pb-3 pt-9">
        <div className="mb-3 flex items-center justify-between px-1 pt-1 text-[10px] text-muted">
          <span className="latin">9:41</span>
          <span className="h-1.5 w-10 rounded-full bg-navy/20" />
        </div>
        <div className="mb-3 flex items-end justify-between px-1">
          <div>
            <p className="text-[11px] font-semibold tracking-[0.18em] text-gold uppercase">AQDAR</p>
            <p className="text-lg font-extrabold leading-none text-navy">عَقدار</p>
          </div>
          <span className="rounded-full bg-teal-soft px-2 py-1 text-[10px] font-semibold text-teal">EGP</span>
        </div>
        <div className="mb-3 rounded-2xl border border-line bg-white px-3 py-2.5 text-sm text-muted">
          {dict.phone.search}
        </div>
        <div className="mb-3 flex gap-1.5">
          {[dict.phone.resale, dict.phone.distressed, dict.phone.map].map((chip, index) => (
            <span
              key={chip}
              className={`rounded-full px-2.5 py-1 text-[11px] font-semibold ${
                index === 0 ? "bg-teal text-white" : "bg-white text-navy ring-1 ring-line"
              }`}
            >
              {chip}
            </span>
          ))}
        </div>
        <article className="mb-2 overflow-hidden rounded-2xl bg-white shadow-[0_10px_24px_rgba(20,40,63,0.08)]">
          <div className="h-28 bg-[linear-gradient(135deg,#1b3a4e,#087e82_58%,#c5985f)]" />
          <div className="p-3">
            <p className="text-sm font-bold text-navy">{dict.phone.listingOneTitle}</p>
            <p className="mt-0.5 text-xs text-muted">{dict.phone.listingOneMeta}</p>
            <p className="mt-2 text-sm font-extrabold text-teal">{dict.phone.listingOnePrice}</p>
          </div>
        </article>
        <article className="overflow-hidden rounded-2xl bg-white shadow-[0_10px_24px_rgba(20,40,63,0.08)]">
          <div className="h-20 bg-[linear-gradient(135deg,#c5985f,#14283f_70%)]" />
          <div className="p-3">
            <p className="text-sm font-bold text-navy">{dict.phone.listingTwoTitle}</p>
            <p className="mt-0.5 text-xs text-muted">{dict.phone.listingTwoMeta}</p>
            <p className="mt-2 text-sm font-extrabold text-teal">{dict.phone.listingTwoPrice}</p>
          </div>
        </article>
        <nav className="absolute inset-x-3 bottom-3 grid grid-cols-5 rounded-2xl bg-navy px-1 py-2 text-[9px] text-white/70">
          {[dict.phone.home, dict.phone.explore, dict.phone.sell, dict.phone.saved, dict.phone.account].map(
            (item, index) => (
              <span
                key={item}
                className={`flex flex-col items-center gap-1 ${index === 2 ? "text-gold" : ""} ${index === 0 ? "text-white" : ""}`}
              >
                <span className={`h-1.5 w-1.5 rounded-full ${index === 2 ? "bg-gold" : "bg-white/40"}`} />
                {item}
              </span>
            ),
          )}
        </nav>
      </div>
    </div>
  );
}
