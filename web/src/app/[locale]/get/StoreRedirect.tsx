"use client";

import { useEffect } from "react";
import { site } from "@/lib/site";

export function StoreRedirect({ message }: { message: string }) {
  useEffect(() => {
    const target = site.downloadUrl || site.appStoreUrl;

    if (!target) {
      return;
    }

    const ua = navigator.userAgent;
    const isIos = /iPhone|iPad|iPod/i.test(ua);
    const isAndroid = /Android/i.test(ua);

    if (isIos || (!isAndroid && target)) {
      window.location.replace(target);
      return;
    }

    if (isAndroid && site.playStoreUrl) {
      window.location.replace(site.playStoreUrl);
    }
  }, []);

  return <p className="sr-only">{message}</p>;
}
