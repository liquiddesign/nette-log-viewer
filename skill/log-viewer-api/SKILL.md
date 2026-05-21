---
name: log-viewer-api
description: Use this skill when the user asks to read, search, browse, or download Tracy logs of a Nette application that has the liquiddesign/nette-log-viewer package installed. The skill exposes the log directory as a JSON REST API reachable via curl — list files, view paginated content, search inside files, and download raw logs. Trigger when the user says things like "look at the log", "find this error in logs", "what's in exception.log", "check tracy logs", "tail the log file", or asks to debug a Nette app via its logs.
---

# Log Viewer API

The `liquiddesign/nette-log-viewer` package exposes Tracy logs through a JSON REST API. Use it to read logs of a Nette application programmatically — directory listing, paginated viewing, search with context, and raw download — all via `curl`.

## Authentication

The API is gated by Tracy debug mode. You do not pass any token: if `Tracy\Debugger::isEnabled()` returns true for the incoming request, the API responds; otherwise it returns `403`. In practice this is usually driven by an IP allowlist, but it can also depend on cookies, environment variables, or explicit `Debugger::enable()` calls in the app — that is the app's concern, not yours.

If you get `403 {"error":"Access denied"}`, surface it to the user. The fix is on the server side (Tracy debug-mode configuration for the IP/cookie the requests originate from). Do not retry.

## Base URL

The base URL must be supplied by the user (e.g. `https://app.example.com/log-viewer/api`). If you do not know it, ask. Never guess hostnames.

## Endpoints

| Endpoint | Required params | Optional params | Returns |
|---|---|---|---|
| `GET /list` | — | `path`, `page=1`, `search`, `itemsPerPage=100` | directory listing |
| `GET /stat` | `file` | — | file metadata (size, totalPages) |
| `GET /view` | `file` | `page=1` | paginated content (100 KB chunks) |
| `GET /search` | `file`, `q` | `context=5`, `direction=both\|before\|after` | content around first match |
| `GET /download` | `file` | — | raw file bytes |

### Parameters

- `path` / `file` — relative to the log directory. Use forward slashes (`cron/2024-05-21.log`). Directory traversal (`..`) is stripped server-side.
- `page` — 1-based. `view` paginates by 100 KB chunks aligned on line boundaries.
- `search` (on `/list`) — case-insensitive substring filter on file names.
- `q` (on `/search`) — case-insensitive substring search inside the file. Returns the first match plus `context` lines.
- `direction` — `before` (only lines before the match), `after` (only lines after), `both` (default).
- `context` — number of context lines (1–300, default 5).
- `itemsPerPage` — 1–1000, default 100.

### Response shape

All responses are JSON except `/download`, which streams raw bytes.

Success example (`/view`):
```json
{
  "file": "exception.log",
  "page": 1,
  "totalPages": 3,
  "chunkSize": 102400,
  "fileSize": 287654,
  "lastModified": 1716284400,
  "isHtml": false,
  "displayedSize": 102398,
  "content": "[2026-05-21 10:00:01] ERROR: ..."
}
```

Error example:
```json
{ "error": "File not found", "code": 400 }
```

HTTP status codes: `200` OK, `400` bad path/parameter, `403` debug mode disabled, `500` log directory not configured.

## Workflow

Always start with `/list` (or `/stat` if you know the file) — do **not** start with `/view` on a blind path. Files can be huge; `/stat` tells you how many pages to expect.

Typical loop for "find this error":
1. `GET /list?search=2026-05-21` — find today's logs.
2. `GET /stat?file=<name>` — check size and `totalPages`.
3. `GET /search?file=<name>&q=<error keyword>&context=20` — locate the first occurrence with context.
4. If you need surrounding pages, `GET /view?file=<name>&page=<n>` and walk pages.

Tracy HTML dumps (`*.html` files) return as `isHtml: true` and are loaded whole. Files larger than 5 MB come back with `truncated: true` and empty content — use `/download` to fetch them. Do not paginate HTML, do not search HTML (search returns `400`).

## Curl recipes

Replace `$BASE` with the user's base URL (e.g. `BASE='https://app.example.com/log-viewer/api'`).

```bash
# 1. What's in the log dir?
curl -s "$BASE/list" | jq

# 2. Filter to today's files
curl -s "$BASE/list?search=2026-05-21" | jq '.items[].name'

# 3. Inspect a file before reading
curl -s "$BASE/stat?file=exception.log" | jq

# 4. Read first chunk
curl -s "$BASE/view?file=exception.log&page=1" | jq -r .content

# 5. Walk all pages of a file
TOTAL=$(curl -s "$BASE/stat?file=exception.log" | jq .totalPages)
for p in $(seq 1 $TOTAL); do
  curl -s "$BASE/view?file=exception.log&page=$p" | jq -r .content
done

# 6. Find first occurrence of "Fatal" with 30 lines of context after
curl -s "$BASE/search?file=exception.log&q=Fatal&context=30&direction=after" | jq

# 7. Download raw file
curl -s -o ./exception.log "$BASE/download?file=exception.log"

# 8. Subdirectory listing (e.g. Tracy's exception dumps)
curl -s "$BASE/list?path=exception" | jq
```

## URL-encoding files and paths

Tracy file names often contain spaces, brackets, or unicode. Always pass `file=` values through URL-encoding. With `curl` use `--data-urlencode`:

```bash
curl -s -G "$BASE/view" \
  --data-urlencode "file=exception/exception--2026-05-21--10-00--abc123.html" \
  --data-urlencode "page=1"
```

## Heuristics

- **"Just look at the log"** → call `/list` first, then `/stat` on the newest entry, then `/view?page=1`.
- **"Find error X"** → use `/search?q=X&context=20`. If `found: false`, try other recent files from `/list`.
- **HTML Tracy dump** → call `/view` once (whole file in `content`), do not paginate, do not search.
- **File suspiciously large** → check `totalPages` from `/stat`. If > 20 pages, prefer `/search` over walking all pages.
- **Multiple matches needed** → API returns only the first match; you must walk pages with `/view` or restart `/search` with a tighter `q`.
- **Output is JSON** — parse with `jq` and surface only the relevant fields. Do not echo the whole JSON envelope to the user.

## Don't

- Don't call `/view` without first checking `/stat` if the file might be large.
- Don't try to delete or write logs. The API is read-only.
- Don't retry on `403` — escalate to the user. The IP needs to be whitelisted, no amount of retries will fix it.
- Don't invent base URLs. Ask the user.
