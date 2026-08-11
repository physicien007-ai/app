# Arrowword Backend (PHP + MySQL)

No CLI needed for day-to-day use. A one-page web installer sets everything
up, and a web-based admin panel handles packs and puzzles — including a
live grid preview, a reference-image panel for transcribing scanned
puzzles, and full support for dual-arrow cells.

## Install (like a WordPress install)

1. Upload this whole folder to your host.
2. In cPanel → MySQL® Databases: create a database and a user, then use
   "Add User to Database" to attach the user with **all privileges**. Note
   the full (prefixed) database name and username — cPanel usually prefixes
   both with your account name, e.g. `myuser_arrowwords`.
3. Visit `https://your-domain.com/backend/install/` in a browser.
4. Fill in the DB credentials from step 2, plus a username/password for
   yourself — submit once. The installer tests the connection, creates the
   `packs`, `puzzles`, and `admin_users` tables, creates your login, and
   writes `config.php` for you. No `mysql` CLI, no manual SQL, no editing
   PHP files by hand.
5. **Delete (or rename) the `install/` folder** once it says "تم التثبيت
   بنجاح" — it refuses to run again once `config.php` exists, but removing
   it entirely is one less thing exposed.
6. Log in at `admin/login.php`.

(`schema.sql` is still there if you'd rather set up the tables manually via
phpMyAdmin — the installer and the manual route produce the same schema.)

## Using the admin panel

- **Dashboard** (`admin/index.php`) — lists every pack with its puzzle
  count and current version.
- **Pack page** — create/rename a pack, see its puzzles, add a new one.
- **Puzzle editor** — this is the main tool:
  - A row per word: direction (RTL/LTR/DOWN/UP), start row/col, answer,
    clue text. Add/remove rows freely.
  - **Live preview** updates as you type (debounced), rendered by the
    exact same `Validator::buildGrid()` PHP code that runs at save time —
    if the preview is clean, the save will succeed, guaranteed. It also
    shows dual-arrow cells exactly as the app will.
  - **Reference image panel** — upload a photo/scan of the newspaper
    puzzle. It's *display only*: no OCR, no auto-generation of the grid.
    Real Arabic handwritten/printed puzzle-image recognition isn't
    something I could responsibly claim to automate reliably in a PHP
    scaffold like this — so instead the image just sits next to the word
    table so you can transcribe it by eye, which is both simpler and far
    more accurate than a shaky auto-detector for this kind of content.
  - Save is disabled until the live preview is fully valid (no empty
    cells, no conflicts).
- **Every save auto-bumps the parent pack's `version`** — that's the
  entire signal the app's sync logic needs. You never set a version number
  by hand.

## Connecting to the app

Nothing to do here — `api/packs.php` and `api/pack.php` already read from
the same tables the admin panel writes to. Point the React Native app's
`API_BASE` (in `src/api.ts`) at `https://your-domain.com/backend/api/` and
it picks up whatever you publish through the admin panel automatically.

## The CLI import script still exists

`scripts/import_pack.php` is still there for bulk/scripted imports (e.g. if
you later write a script that converts a batch of digitized puzzles to
JSON files) — it uses the same `Validator::buildGrid()`. Not required for
normal day-to-day use now that the admin panel exists.


## The content format — this is your entire digitization pipeline

Each puzzle is a list of words. Each word needs only **five fields**:

| field       | meaning                                                        |
|-------------|-------------------------------------------------------------------|
| `direction` | `"RTL"` (normal Arabic across), `"LTR"`, `"DOWN"`, `"UP"`        |
| `startRow`  | row of the first letter (0 = top)                                |
| `startCol`  | column of the first letter (0 = left)                             |
| `answer`    | the answer, one Arabic letter per grid cell (e.g. `"كتاب"`)      |
| `clueText`  | the clue phrase printed in the clue cell                          |

The clue cell's position and every letter cell's position are computed
automatically (`Validator.php` / the app's `gridBuilder`) — you never
compute coordinates for the clue cell by hand. This is deliberately the
smallest possible format for someone transcribing a scanned newspaper page:
essentially one spreadsheet row per word.

Full pack JSON shape (see `sample_pack.json` for a real example):

```json
{
  "id": "pack-2026-08-09",
  "title": "أرشيف جريدة الصباح - عدد 9 أغسطس",
  "version": 1,
  "puzzles": [
    {
      "id": "puzzle-1",
      "title": "كلمات مسهمة رقم 245",
      "rows": 10,
      "cols": 5,
      "words": [
        { "id": "a1", "direction": "RTL", "startRow": 0, "startCol": 3, "answer": "كتاب", "clueText": "نقرأ فيه ونتعلم منه" }
      ]
    }
  ]
}
```

## Publishing a new/updated pack

**Normal path:** log into `admin/`, open the pack, click "لغز جديد" (or edit
an existing one), fill in the word table, watch the live preview go green,
save. The pack's version bumps automatically — every installed app picks
up the change on next launch or pull-to-refresh, no app-store release
involved.

**Bulk/scripted path (optional):** if you ever write a script that
generates pack JSON files programmatically, `php scripts/import_pack.php
path/to/pack.json` still works, validates with the same `Validator`, and
upserts the same way.

## Suggested next steps

- Add an `Authorization` check (API key) to `api/packs.php` / `api/pack.php`
  once this isn't just for your own testing — they're fully public/read-only
  right now.
- `POST /v1/progress` + accounts, if you want best times/solved state to
  sync across a player's devices (the RN app currently keeps that locally
  via AsyncStorage only).
- If transcribing scans by eye turns out to be the bottleneck once you're
  doing this at real volume, a next step worth considering is a proper
  OCR-assisted pass (e.g. a separate script using a vision API) that
  pre-fills the word table for a human to correct — rather than fully
  automating it, which isn't realistic to get right unsupervised for this
  kind of dense, small-print content.
