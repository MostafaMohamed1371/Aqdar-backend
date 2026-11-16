"use client";

import { useEffect, useState } from "react";

type ScrollToTopProps = {
  label: string;
};

export function ScrollToTop({ label }: ScrollToTopProps) {
  const [visible, setVisible] = useState(false);

  useEffect(() => {
    let lastY = window.scrollY;

    const onScroll = () => {
      const y = window.scrollY;
      const scrollingUp = y < lastY;
      setVisible(scrollingUp && y > 320);
      lastY = y;
    };

    window.addEventListener("scroll", onScroll, { passive: true });
    return () => window.removeEventListener("scroll", onScroll);
  }, []);

  if (!visible) {
    return null;
  }

  return (
    <button
      type="button"
      aria-label={label}
      onClick={() => window.scrollTo({ top: 0, behavior: "smooth" })}
      className="fixed bottom-6 end-6 z-50 flex h-12 w-12 items-center justify-center rounded-full bg-teal text-white shadow-[0_12px_30px_rgba(8,126,130,0.4)] transition hover:bg-teal-2 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal"
    >
      <svg viewBox="0 0 24 24" className="h-5 w-5" fill="none" stroke="currentColor" strokeWidth="2.2" aria-hidden="true">
        <path d="M12 19V5M5 12l7-7 7 7" strokeLinecap="round" strokeLinejoin="round" />
      </svg>
    </button>
  );
}
