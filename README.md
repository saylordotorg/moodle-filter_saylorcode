# Saylor Code Studio — embed filter (`filter_saylorcode`)

Resolves Saylor Code Studio embed tokens in Moodle content, so a coding exercise can appear
inside a Page, Book chapter or Lesson page without its definition being copied there.

**Status: alpha, Phase 1 vertical slice.**

## Requirements

| | |
|---|---|
| Moodle | 4.5 (build 2024100700) |
| PHP | 8.1 – 8.3 |
| Depends on | `local_saylorcode` |

## Usage

Write a token anywhere filtered content is allowed:

```
[[saylorcode:exercise=CS101-U05-E03]]
```

With options:

```
[[saylorcode:exercise=CS101-U05-E03;mode=full;version=7;height=500]]
```

| Attribute | Values | Default |
|---|---|---|
| `exercise` | A stable exercise ID — **required** | — |
| `mode` | `compact`, `full`, `link` | `compact` |
| `version` | `latest`, or a version number | `latest` |
| `height` | 300, 400, 500, 600, 700, 800 | unset |
| `showinstructions` | `true` / `false` | `true` |
| `allowfullscreen` | `true` / `false` | `true` |

Enable the filter at *Site administration → Plugins → Filters → Manage filters*.

## Why the parser is strict

The filter runs on text that anyone with editing rights can supply, and tokens get copied
between courses by hand. So the parser is a whitelist, not a transformer:

- **Unknown attributes are discarded, not passed through.** Writing
  `runner=http://evil.example.org` or `apikey=…` or `onload=…` into a token has no effect —
  those names aren't recognised, so they never reach a template. A token can carry a
  *reference* and *presentation preferences*, and nothing else (spec §11.2).
- **Unsupported values fall back rather than echo.** An unrecognised `mode` becomes `compact`;
  it is never reflected into the page.
- **`height` is a fixed set, not a number.** Arbitrary heights are a layout hazard on small
  screens and buy nothing an approved step doesn't.
- **The token pattern excludes `<`, `>`, `[` and `]`**, so a token cannot span markup
  boundaries.

`tests/local/embed_token_test.php` asserts each of these adversarially.

## Behaviour worth knowing

**A broken reference is shown only to people who can fix it.** If a token names no valid
exercise, users with editing rights get a warning naming the offending token; students get
nothing at all. A diagnostic they can't act on is noise at best (spec §11.2).

**Signed-out and guest visitors get a link, not an editor.** There's nowhere to persist their
work, so rather than handing them a workspace that silently loses code, the filter says so and
links to the stand-alone exercise (spec §8.5).

**There is a server-rendered fallback.** The `<noscript>` path and the "Open the exercise" link
are in the server output, not injected by JavaScript, so a reader without JS is never left
looking at an empty box.

## Licence

GPL-3.0-or-later, matching Moodle.
