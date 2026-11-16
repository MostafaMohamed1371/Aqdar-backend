import { appDownloadUrl, site } from "@/lib/site";

type StoreButtonsProps = {
  overline: string;
  iosLabel: string;
  androidLabel: string;
  getHref: string;
  light?: boolean;
};

export function StoreButtons({ overline, iosLabel, androidLabel, getHref, light = false }: StoreButtonsProps) {
  const download = appDownloadUrl(getHref);
  const android = site.playStoreUrl;

  return (
    <div className="flex flex-wrap gap-3">
      <StoreLink href={download} overline={overline} label={iosLabel} store="ios" light={light} />
      {android ? (
        <StoreLink href={android} overline={overline} label={androidLabel} store="android" light={light} />
      ) : null}
    </div>
  );
}

function StoreLink({
  href,
  overline,
  label,
  store,
  light,
}: {
  href: string;
  overline: string;
  label: string;
  store: "ios" | "android";
  light: boolean;
}) {
  return (
    <a
      href={href}
      target="_blank"
      rel="noopener noreferrer"
      className={`inline-flex min-w-[11.5rem] items-center gap-3 rounded-2xl px-4 py-3 transition ${
        light
          ? "bg-white text-navy hover:bg-cream"
          : "bg-navy text-white hover:bg-navy-mid"
      }`}
    >
      {store === "ios" ? <AppleIcon /> : <PlayIcon />}
      <span className="leading-tight">
        <span className={`block text-[10px] uppercase tracking-[0.16em] ${light ? "text-muted" : "text-white/55"}`}>
          {overline}
        </span>
        <span className="block text-sm font-semibold">{label}</span>
      </span>
    </a>
  );
}

function AppleIcon() {
  return (
    <svg viewBox="0 0 24 24" className="h-6 w-6 fill-current" aria-hidden="true">
      <path d="M16.7 12.6c0-3 2.5-4.4 2.6-4.5-1.4-2.1-3.6-2.4-4.4-2.4-1.9-.2-3.6 1.1-4.6 1.1s-2.4-1.1-4-1c-2 .1-3.9 1.2-4.9 3-2.1 3.7-.5 9.1 1.5 12.1 1 1.5 2.2 3.1 3.8 3.1 1.5 0 2.1-1 3.9-1s2.3 1 4 1 2.7-1.5 3.7-2.9c1.2-1.7 1.7-3.3 1.7-3.4-.1 0-3.3-1.3-3.3-5.1zM14.4 4.3c.8-1 1.4-2.4 1.2-3.8-1.2 0-2.6.8-3.4 1.8-.8.9-1.5 2.3-1.3 3.7 1.4.1 2.7-.7 3.5-1.7z" />
    </svg>
  );
}

function PlayIcon() {
  return (
    <svg viewBox="0 0 24 24" className="h-6 w-6 fill-current" aria-hidden="true">
      <path d="M4 3.5v17l12.5-8.5L4 3.5zm13.2 6.3 2.4-1.6c.9-.6 1.4 0 1.4.7v6.2c0 .7-.5 1.3-1.4.7l-2.4-1.6-1.5-1.1 1.5-1.3z" />
    </svg>
  );
}
