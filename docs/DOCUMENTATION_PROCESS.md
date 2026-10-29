# After each finished phase

Keep three artifacts current. Do not start the next phase until they list everything that phase added.

## 1. `docs/API.md`

Add a **Phase N** section with:

- Goal (one paragraph)
- New / changed / locked HTTP routes
- Request body, auth, status codes
- Example JSON (no secrets, no real PII)
- Rate limits if new

Update the summary table at the top of `API.md`.

## 2. `docs/DASHBOARD.md`

For every Blade screen shipped in that phase:

- Mockup id (`ADM-04`)
- Route
- Permission
- Create / edit / delete / other actions

## 3. Postman (`postman/AQDAR-API.postman_collection.json`)

Add a folder named **`Phase N — …`**.

For each new API route:

1. One request in that folder
2. Realistic example body
3. `Accept: application/json`
4. Public routes: `"auth": { "type": "noauth" }`
5. Protected routes: collection Bearer `{{auth_token}}`
6. Login / OTP verify: test script that stores `data.token` in `auth_token`
7. Locked routes: folder note that 401/403 is expected

If the phase adds environment keys (listing id, inquiry id), add them to `AQDAR-Local.postman_environment.json` and document them in `postman/README.md`.

## 4. README

Update the **Current delivery** line and the API / admin tables in the root `README.md`.

## Checklist (copy into the phase PR)

- [ ] `docs/API.md` — new endpoints and examples
- [ ] `docs/DASHBOARD.md` — new screens
- [ ] Postman folder `Phase N` with every new route
- [ ] Environment variables if needed
- [ ] Root `README.md` tables
- [ ] PHPUnit (and E2E if the phase has a screen)

## What not to document as a live API

- Staff Blade forms (they are in `DASHBOARD.md`, not Postman)
- Locked Structer leftovers (`POST /settings`, `POST /users` from the marketplace token) — keep them only under **Locked** so clients do not call them
- OTP codes, passwords, or production sales numbers
