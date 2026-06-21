# Community System

Reference: [game-summary.md](../game-summary.md#214-community-system)

Purpose: Document leaderboards, private messages, forums, content moderation, and social features.

---

## Overview

The community system covers:
1. **Private Messages** — player-to-player inbox/outbox within the same kingdom.
2. **Forum** — kingdom-scoped threaded discussions with categories.
3. **News Articles** — kingdom-scoped or global announcements.
4. **Leaderboards & Public Profiles** — planned for a future phase.

All community interactions are **kingdom-scoped**: players can only send messages to or create threads for players within their own kingdom.

---

## Private Messages

### Model (`Message` entity)

| Field | Type | Description |
|-------|------|-------------|
| `sender_user_id` | FK → User | Sending player |
| `receiver_user_id` | FK → User | Receiving player |
| `sender_team_id` | FK → Team (nullable) | Sender's team at send time (snapshot) |
| `receiver_team_id` | FK → Team (nullable) | Receiver's team at send time (snapshot) |
| `subject` | string | Message subject (filtered) |
| `body` | text | Message body (filtered) |
| `read_at` | datetime (nullable) | When the receiver first read the message |
| `sent_at` | datetime | Send timestamp |
| `deleted_by_sender` | bool | Sender has soft-deleted the message |
| `deleted_by_receiver` | bool | Receiver has soft-deleted the message |

### Rules
- A player cannot send a message to themselves.
- Sender and receiver must belong to the same kingdom.
- Team references are **snapshots** captured at send time; they do not change if either player later switches teams.
- All subject and body content passes through `ContentFilterService` before being stored.
- Messages are **soft-deleted**: each party can delete independently. When both parties have deleted the message, it is permanently removed from the database.
- Reading a message (via `GET /api/v1/messages/{id}`) sets `read_at` to the current timestamp.

### API

| Method | Path | Purpose |
|--------|------|---------|
| GET | `/api/v1/messages` | Inbox (excludes deleted by receiver) |
| GET | `/api/v1/messages/{id}` | Read message (sets `read_at`) |
| POST | `/api/v1/messages` | Send a message (`receiver_user_id`, `subject`, `body`) |
| GET | `/api/v1/messages/recipients` | List eligible recipients in the same kingdom |
| DELETE | `/api/v1/messages/{id}` | Soft-delete for the requesting player |

---

## Forum

### Model (`ForumThread` + `ForumPost` entities)

**ForumThread:**

| Field | Type | Description |
|-------|------|-------------|
| `kingdom_id` | FK → Kingdom | Kingdom the thread belongs to |
| `category` | string | Thread category (free-form string, client-defined) |
| `title` | string | Thread title (filtered) |
| `author_user_id` | FK → User | Player who created the thread |
| `author_team_id` | FK → Team (nullable) | Author's team at creation time (snapshot) |
| `created_at` | datetime | Creation timestamp |
| `is_pinned` | bool | Whether the thread is pinned (admin use) |
| `is_locked` | bool | Locked threads do not accept new replies |

**ForumPost:**

| Field | Type | Description |
|-------|------|-------------|
| `thread_id` | FK → ForumThread | Parent thread |
| `author_user_id` | FK → User | Player who wrote the post |
| `author_team_id` | FK → Team (nullable) | Author's team at post time (snapshot) |
| `body` | text | Post content (filtered) |
| `created_at` | datetime | Post timestamp |

### Rules
- Players can only create threads and posts in their own kingdom's board.
- Authors are identified by `User`; the optional team snapshot is shown in the UI when present.
- New threads automatically create the **first post** from the author's body text.
- Replies to a locked thread are rejected with a `DomainException`.
- **Thread locking** is available to the thread **author only** via `POST /api/v1/forum/threads/{id}/lock` with `{"lock": true}` or `{"lock": false}`.
- All `title`, `body`, and `category` fields are passed through `ContentFilterService`.

### API

| Method | Path | Purpose |
|--------|------|---------|
| GET | `/api/v1/forum/threads` | List threads for player's kingdom (optional `?category=` filter) |
| POST | `/api/v1/forum/threads` | Create thread (`title`, `body`, `category` required) |
| GET | `/api/v1/forum/threads/{id}` | Thread detail including all posts |
| POST | `/api/v1/forum/threads/{id}/posts` | Reply to a thread (`body` required) |
| POST | `/api/v1/forum/threads/{id}/lock` | Lock or unlock thread (author only; `{"lock": true/false}`) |

---

## Content Moderation (`ContentFilterService`)

All user-generated text (message subjects, message bodies, thread titles, post bodies) is automatically filtered by `App\Service\Community\ContentFilterService` before being stored.

### How it works
The filter replaces matched words with asterisks (`***`). Matching is:
- **Case-insensitive**
- **Unicode-safe** (handles diacritics correctly)
- Applied per word in the blacklist using a full-word-contains match (not word-boundary anchored)

### Blacklist
The current blacklist contains Czech and English profanities. The list is defined as a hardcoded `const BLACKLIST` in `ContentFilterService` and must be updated in code if extended.

**Czech words currently filtered:** `debil`, `blbec`, `curak`, `čurák`, `picus`, `píč`, `pica`, `píča`, `kokot`, `hovno`, `prdel`, `srac`, `sráč`

**English words currently filtered:** `fuck`, `shit`, `asshole`, `bitch`, `crap`, `cunt`, `bastard`, `dick`

> **Note:** The current implementation is a simple blacklist. It does not handle obfuscation (e.g. `f*ck`, `sh1t`) or contextual moderation. Extending the filter to cover more languages or use a third-party service is a future consideration.

---

## Leaderboards & Public Profiles

> [!NOTE]
> **Planned for a future phase.** The `GET /api/v1/leaderboards` and `GET /api/v1/players/{id}/profile` endpoints are not yet implemented. Leaderboard data will be derived from `LeagueStanding`; player profiles will project `User + Team`.

---

## News Articles

### Implementation Status

| Layer | Status |
|-------|--------|
| **Entity** | Implemented — `App\Entity\Community\NewsArticle` (`news_article` table) |
| **Repository** | Implemented — `NewsArticleRepository` (published listing, pagination) |
| **Public Web UI** | Implemented — `GET /news` (`Web\NewsController`) |
| **Admin / CMS** | Not implemented — articles must be seeded via DB or future admin UI |

### Model (`NewsArticle` entity)

| Field | Type | Description |
|-------|------|-------------|
| `kingdom_id` | FK → Kingdom (nullable) | Kingdom scope; `null` = global article |
| `title` | string | Article title |
| `content` | text | Article body |
| `published_at` | datetime | Publication timestamp (articles with future `published_at` are hidden) |

### Public routes

| Method | Path | Purpose |
|--------|------|---------|
| GET | `/news` | Paginated news archive (full content per item; no detail page) |

Homepage links to the archive via `homepage.news_archive_link`. Guest navbar includes a News link.
