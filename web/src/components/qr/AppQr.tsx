"use client";

import { useEffect, useState } from "react";
import QRCode from "qrcode";
import type { Locale } from "@/lib/i18n";
import { localePath } from "@/lib/i18n";
import { appDownloadUrl } from "@/lib/site";

type AppQrProps = {
  locale: Locale;
  label: string;
  className?: string;
};

export function AppQr({ locale, label, className = "" }: AppQrProps) {
  const [svg, setSvg] = useState("");

  useEffect(() => {
    const url = appDownloadUrl(`${window.location.origin}${localePath(locale, "get")}`);

    QRCode.toString(url, {
      type: "svg",
      margin: 1,
      width: 240,
      color: { dark: "#14283f", light: "#ffffff" },
    }).then(setSvg);
  }, [locale]);

  if (!svg) {
    return (
      <div
        className={`aspect-square w-full animate-pulse rounded-2xl bg-cream ${className}`}
        aria-hidden="true"
      />
    );
  }

  return (
    <div
      className={`qr-box overflow-hidden rounded-2xl bg-white p-2 ${className}`}
      role="img"
      aria-label={label}
      dangerouslySetInnerHTML={{ __html: svg }}
    />
  );
}
