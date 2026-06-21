# Public Pages (Unauthenticated Section)

Reference: [screens-overview.md](../screens-overview.md)

**When:** Before login — visible to all visitors (unauthenticated users)

---

## Site Structure Overview

The site is divided into two main sections:

- **Public** (`/`) — Open to all visitors. Contains Homepage, wiki (help), and news archive.
- **Inside** (`/app/...`) — Authenticated game area. Dashboard (`/app/dashboard`) serves as the main page for logged-in users. All other game screens live here.

Authentication (login/register) is implemented as **standalone pages** (`/login`, `/register`). Modal-window UX is a planned future enhancement but is not the current implementation.

---

## Language Switcher

- Displayed on **all public pages** (homepage, news, wiki) — not in the authenticated inside section.
- Supported languages: **Czech (`cs`)** and **English (`en`)**.
- Switching language updates the URL locale prefix (e.g. `/cs/`, `/en/`) or sets a cookie; Symfony Translator picks it up on the next request.
- The switcher is a simple toggle/dropdown in the site header, always visible on public pages.
- Authenticated players manage their language in **Profile / Settings** (the inside section has no switcher).

---

## Homepage

### Displayed Information:
- General project description — what Fantager is about
- Latest news feed (most recent items displayed in full, with link to full archive)
- Login / Register buttons (navigate to standalone auth pages)

### Possible Actions/Buttons:
- **Login** — navigate to `/login` page
- **Register** — navigate to `/register` page
- **View News Archive** — navigate to full news list
- **Wiki / Help** — navigate to help/wiki section

### Navigation between auth pages:
- Login page has a "Create new account" link → navigates to Register page
- Register page has a "Already have an account? Login" link → navigates to Login page

> **Note**: Modal-window UX is a planned future enhancement. Current implementation uses standalone pages.

### Backend Requirements:
- Endpoint for latest news (paginated, limited count for homepage)
- Static or CMS-driven homepage content

---

## News Archive

### Displayed Information:
- Paginated list of news items — each item is displayed in full (title, date, full text); no detail page or excerpt

### Possible Actions/Buttons:
- **Pagination** — navigate pages
- **Back to Homepage** — return to main page

### Backend Requirements:
- `GET /news` — paginated news list (full content per item) — **implemented** (`NewsArticle` entity + `Web\NewsController`)
- Admin interface for creating/editing news (future)

---

## Wiki / Help

### Displayed Information:
- Help articles and game guides
- Categorized help topics
- Search within wiki

### Possible Actions/Buttons:
- **Search** — find help articles
- **Browse categories** — navigate help topics
- **Back to Homepage** — return to main page

### Backend Requirements:
- Content management for help articles (static files or DB-driven)
- Search endpoint for wiki content (future)

---

## Sections to fill:
- Exact URL routing (`/`, `/news`, `/wiki`)
- SEO requirements (meta tags, open graph)
- News item data model (title, content, published_at, author)
- Wiki content structure and categorization
- Responsive design considerations for modals
- Analytics / tracking on public pages
