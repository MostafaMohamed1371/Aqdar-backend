# AQDAR web

Public marketing site for **عَقدار** — Egypt’s Arabic-first exit and resale marketplace.

```bash
cd web
npm install
npm run dev
```

Open [http://localhost:3000](http://localhost:3000). Arabic is the default (`/ar`); English is `/en`.

Copy `.env.example` to `.env.local` and set store URLs when the app is on the App Store / Google Play. The QR code then points at those links (or at `/get`, which sends iOS and Android users to the right store).
