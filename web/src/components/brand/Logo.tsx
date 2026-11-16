import type { Locale } from "@/lib/i18n";

type LogoProps = {
  locale: Locale;
  variant?: "light" | "dark";
  size?: "sm" | "md";
};

export function Logo({ locale, variant = "dark", size = "md" }: LogoProps) {
  const light = variant === "light";
  const mark = size === "sm" ? "h-9 w-9" : "h-11 w-11";

  return (
    <span className="inline-flex items-center gap-2.5">
      <svg className={mark} viewBox="0 0 64 64" aria-hidden="true">
        <path d="M32 4 60 26v34H4V26z" fill={light ? "#FBF8F2" : "#14283F"} />
        <path d="M32 4 60 26v34H47V31L32 19z" fill="#087E82" />
        <path d="M4 27 32 5l4 3L8 30v30H4z" fill="#C5985F" />
        <text
          x="27"
          y="53"
          textAnchor="middle"
          fontFamily="Cairo, Tahoma, sans-serif"
          fontWeight="800"
          fontSize="28"
          fill={light ? "#14283F" : "#fff"}
        >
          ع
        </text>
      </svg>
      <span className="leading-none">
        <span
          className={`block text-[1.35rem] font-extrabold tracking-tight ${light ? "text-white" : "text-navy"}`}
        >
          {locale === "ar" ? "عَقدار" : "AQDAR"}
        </span>
        <span
          className={`mt-0.5 block text-[10px] font-semibold uppercase tracking-[0.28em] ${light ? "text-gold" : "text-teal"}`}
        >
          {locale === "ar" ? "AQDAR" : "عَقدار"}
        </span>
      </span>
    </span>
  );
}
