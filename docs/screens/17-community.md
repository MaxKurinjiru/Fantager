# Community Screen

Reference: [screens/overview-legacy.md](overview-legacy.md#17-community-screen) (historical), [architecture/screen-code-map.md](../architecture/screen-code-map.md), [community-system.md](../systems/community-system.md)

**When:** Kingdom forum, public player/team profiles, and private mail.

**Status:** Live forum hub at `/app/community`. Mail is the **navbar modal**, not a community tab. News is public `/news`. Kingdom rankings are the League **Kingdom Leaderboard** tab — there is no `/api/v1/leaderboards` route. Forum `is_pinned` is **stored and shown** when true, but **unsettable** in the app ([roadmap A6.3](../roadmap/current.md) Cut — no pin/unpin API, no admin pin).

**Live UI:** `templates/community/`, `templates/components/community/`, Stimulus `community_forum`, `community_thread`, `mail`, `player-profile`.

---

## Implemented

### Forum hub (`GET /app/community`)

- Header + **New thread**
- Category nav: all, `general`, `strategy`, `trading`, `bugs` (`?category=`)
- Search (`?q=`)
- Pinned threads (page 1 only, if `is_pinned`), then recent threads (20 per page)
- Thread card: pin/lock icons, title, body preview, category, reply count, last activity, author (opens profile), team emblem/colors
- Empty states for no threads / no search hits

### Thread detail (`GET /app/community/threads/{id}`)

- Title, category, pin/lock badges, author + team, started-at
- Original post (page 1) + paginated replies (20)
- Reply form (hidden when locked)
- **Lock / unlock** — thread **author only** (`POST /api/v1/forum/threads/{id}/lock`)

New thread modal: title, category, body → `POST /api/v1/forum/threads`. Reply → `POST /api/v1/forum/threads/{id}/posts`. Title/body pass `ContentFilterService`. Kingdom-scoped.

### Public profiles (modal)

Opened from author names, league tables, and similar `player-profile` buttons. Same kingdom only.

- `GET /api/v1/players/{id}/profile`
- `GET /api/v1/teams/{id}/profile`

NPC teams have no public player profile (404). Payload **excludes** gold, debt, chemistry, and morale. Typical body:

```json
{
  "user": { "id": 12, "display_name": "Username", "member_since": "2026-06-15T12:00:00Z" },
  "team": {
    "id": 4, "name": "Team Name", "emblem": "🛡️",
    "colors": { "primary": "#111", "secondary": "#222" },
    "fan_base": 1500, "reputation": 200,
    "combatant_count": 8, "trainer_count": 2
  },
  "league": {
    "tier_name": "T1", "group_name": "Group A", "position": 3,
    "points": 15, "played": 6, "wins": 5, "draws": 0, "losses": 1,
    "form": ["W", "W", "L", "W", "W"]
  },
  "is_own_profile": false,
  "can_message": true
}
```

Modal: team identity, fan base, reputation, combatant/trainer counts, league block when present, member since. **Send message** (`community.send_message`) opens the mail compose dialog with the recipient prefilled (`modal:open-compose`).

### Private mail (navbar)

Not a tab on `/app/community`. Navbar mail control + unread badge.

| Method | Path | Purpose |
|--------|------|---------|
| GET | `/api/v1/messages` | Inbox or sent (`folder`, `page`; 10 per page) |
| GET | `/api/v1/messages/unread-count` | Badge |
| GET | `/api/v1/messages/recipients` | Same-kingdom recipients |
| GET | `/api/v1/messages/{id}` | Read (sets `read_at`) |
| POST | `/api/v1/messages` | Send (`receiver_user_id`, `subject`, `body`) |
| DELETE | `/api/v1/messages/{id}` | Soft-delete for the caller |

Compose / read dialogs: `templates/components/community/compose_message_modal.html.twig`, `read_message_modal.html.twig`.

### Backend

| Piece | Location |
|-------|----------|
| Web | `Web\CommunityController` |
| Forum API | `Api\V1\ForumController` |
| Mail API | `Api\V1\MessageController` |
| Profiles | `Api\V1\CommunityController` |
| Domain | `CommunityService`, `ForumThreadHelper`, `PlayerProfileService` |

---

## Planned

Original sketch extras — do not treat as a contract:

- Community tabs for leaderboards, mail, or news
- Dedicated `/api/v1/leaderboards` (use League)
- Pin / unpin in the UI — **cut** (A6.3): field + display only
- Notable heroes, recent match history, win/loss on the public profile
- Email notifications for forum/mail (auth + inactivity mail only today)
- Report / moderator tools beyond author lock and content filter

---

## Frontend

| Piece | Location |
|-------|----------|
| Hub / thread | `templates/community/index.html.twig`, `thread.html.twig` |
| Cards / posts / modals | `templates/components/community/` |
| Stimulus | `community_forum`, `community_thread`, `mail`, `player-profile` |
