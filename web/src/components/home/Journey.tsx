"use client";

import { useState } from "react";
import { Container } from "@/components/layout/Container";
import { SectionHeading } from "@/components/home/SectionHeading";
import type { Dictionary } from "@/lib/i18n";

export function Journey({ dict }: { dict: Dictionary }) {
  const [role, setRole] = useState<"buyer" | "seller">("buyer");
  const steps = role === "buyer" ? dict.journey.buyerSteps : dict.journey.sellerSteps;

  return (
    <section id="journey" className="scroll-mt-24 bg-navy-deep py-20 text-white">
      <Container>
        <SectionHeading
          invert
          kicker={dict.journey.kicker}
          title={dict.journey.title}
          lead={dict.journey.lead}
        />
        <div className="mx-auto mt-8 flex w-fit rounded-full bg-white/10 p-1 ring-1 ring-white/10">
          <RoleButton active={role === "buyer"} onClick={() => setRole("buyer")}>
            {dict.journey.buyer}
          </RoleButton>
          <RoleButton active={role === "seller"} onClick={() => setRole("seller")}>
            {dict.journey.seller}
          </RoleButton>
        </div>
        <ol className="mt-12 grid gap-4 lg:grid-cols-4">
          {steps.map((step, index) => (
            <li key={step.title} className="rounded-[1.5rem] border border-white/10 bg-white/5 p-6">
              <span className="flex h-10 w-10 items-center justify-center rounded-2xl bg-teal text-sm font-bold">
                {index + 1}
              </span>
              <h3 className="mt-5 text-lg font-extrabold">{step.title}</h3>
              <p className="mt-2 text-sm leading-7 text-white/65">{step.body}</p>
            </li>
          ))}
        </ol>
      </Container>
    </section>
  );
}

function RoleButton({
  active,
  onClick,
  children,
}: {
  active: boolean;
  onClick: () => void;
  children: string;
}) {
  return (
    <button
      type="button"
      onClick={onClick}
      className={`rounded-full px-5 py-2 text-sm font-semibold transition ${
        active ? "bg-teal text-white" : "text-white/70 hover:text-white"
      }`}
      aria-pressed={active}
    >
      {children}
    </button>
  );
}
