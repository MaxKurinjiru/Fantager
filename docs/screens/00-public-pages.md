# Public Pages (Unauthenticated Section)

Reference: [architecture/screen-code-map.md](../architecture/screen-code-map.md), [architecture/routes.md](../architecture/routes.md)

**When:** Before login — visible to visitors who are not in the game shell.

**Live UI:** `templates/layouts/public.html.twig` (guest navbar + `guest_body`). Auth forms on these pages: [00-auth-screens.md](00-auth-screens.md).

---

## Site structure

- **Public** (`/`, `/news`) — open to all visitors. Homepage and paginated news archive.
- **Game** (`/app/...`) — authenticated. Dashboard (`/app/dashboard`) is the landing page after login.

Login/register are a **modal** included from `templates/base.html.twig` for guests. `/login` and `/register` exist as routes that auto-open that modal. Password reset is standalone pages. Modal-on-homepage CTAs are live, not a future enhancement.

Public wiki (`GET /wiki`) has **no controller** — **Planned** (A8.5 stay Planned).

---

## Language switcher

**Implemented**

- **Guest / public navbar** (`templates/components/layout/navbar.html.twig` when `is_guest` is true): Czech (`cs`) / English (`en`) dropdown. Used on homepage, news, login/register (empty shell), and password-reset pages.
- **Authenticated Account Settings** modal: the same two locales via a locale picker. Opened from the game navbar player menu — the game navbar itself has **no** locale dropdown.
- Both hit `GET /change-locale/{locale}` (`Web\LocaleController`): session `_locale`; if the user is logged in, also persists `User.locale`. Redirects back to the local referer.

**Planned**

- Locale control on a public wiki page (no wiki yet).
- Locale dropdown on the authenticated game navbar (not required while Settings has the picker).

---

## Homepage — Implemented

**Route:** `GET /` → `Web\DefaultController` → `templates/home/index.html.twig`.

### Displayed information

- Title, tagline, and project description (translated `homepage.*` keys)
- Latest **3** published news items (title, date, full text) when any exist
- Link to the news archive

### Actions

- **Play / Register** — `click->auth-modal#openRegister` (href `/register` as fallback)
- **Login** — `click->auth-modal#openLogin` (href `/login` as fallback)
- **Enter game** — if already authenticated, link to `/app/dashboard`
- **News archive** — `GET /news`
- Guest navbar: logo (home), News, locale dropdown, Login

### Backend

- `NewsArticleRepository::findLatestPublished(3)` — no separate homepage CMS

---

## Homepage — Planned

- Wiki / Help link (no `/wiki` route)
- Static or CMS-driven homepage beyond translated Twig + latest news
- SEO extras (Open Graph, dedicated meta beyond the page title)

---

## News archive — Implemented

**Route:** `GET /news` → `Web\NewsController` → `templates/news/index.html.twig`.

### Displayed information

- Paginated list (10 per page). Each item is shown in full (title, published time, kingdom name or “global”, full text). **No** detail page or excerpt.
- Empty state when there are no published articles
- Back to homepage

### Actions

- Pagination (`?page=`)
- Back to homepage

### Backend

- `NewsArticle` (`news_article`: `title`, `content`, `published_at`, optional `kingdom_id`)
- `GET /news` lists published rows (`published_at` not in the future)
- Articles are seeded / inserted in the DB today — **no** in-app admin UI

---

## News archive — Planned

- Admin interface for creating/editing news (stay Planned; adjacent to A8.5, not a live CMS)

---

## Wiki / Help — Planned

`GET /wiki` is listed in [architecture/routes.md](../architecture/routes.md) as **planned** (A8.5 stay Planned). No Twig, no controller, no entity, no search, no guest-navbar Help link.

Original sketch (not live): help articles, categories, search, back to homepage.

## Locked: public wiki stays Planned

`GET /wiki` stays **Planned**. Do not ship a wiki CMS, static help pages, categories, search, or a navbar Help link. Live public content is homepage + `/news` (seed / DB; news admin stays Planned). Unused translation key `nav.wiki` may stay for a later slice. A later product decision is required before adding a wiki.
