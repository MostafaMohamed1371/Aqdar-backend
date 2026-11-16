const TESTFLIGHT_URL = "https://testflight.apple.com/join/6dvpGv5r";

export const site = {
  name: "AQDAR",
  nameAr: "عَقدار",
  url: process.env.NEXT_PUBLIC_SITE_URL ?? "http://localhost:3000",
  appStoreUrl: process.env.NEXT_PUBLIC_APP_STORE_URL || TESTFLIGHT_URL,
  playStoreUrl: process.env.NEXT_PUBLIC_PLAY_STORE_URL ?? "",
  downloadUrl: process.env.NEXT_PUBLIC_APP_DOWNLOAD_URL || TESTFLIGHT_URL,
  salesPhone: process.env.NEXT_PUBLIC_SALES_PHONE || "+201000000001",
  salesHoursAr: process.env.NEXT_PUBLIC_SALES_HOURS_AR || "يومياً 10ص – 10م",
  salesHoursEn: process.env.NEXT_PUBLIC_SALES_HOURS_EN || "Daily 10am – 10pm",
} as const;

export function absoluteUrl(path: string): string {
  return new URL(path, site.url).toString();
}

export function appDownloadUrl(fallback: string): string {
  return site.downloadUrl || site.appStoreUrl || fallback;
}

export function salesHours(locale: "ar" | "en"): string {
  return locale === "en" ? site.salesHoursEn : site.salesHoursAr;
}

/** Digits only, for tel: and wa.me */
export function salesPhoneDigits(): string {
  return site.salesPhone.replace(/\D/g, "");
}

export function salesTelHref(): string {
  return `tel:${site.salesPhone}`;
}

export function salesWhatsAppHref(): string {
  return `https://wa.me/${salesPhoneDigits()}`;
}
