# Community Screen

Reference: [screens-overview.md](../screens-overview.md#17-community-screen)

Purpose: Per-screen API, events, UI data requirements, and implementation notes.

Displayed Information:
- Community Tabs: Leaderboards, Player Profiles, Mail/Messages, News Feed, Forums
- Leaderboards Panel: categories and top players with filters
- Player Profile View: team info, notable heroes, recent matches
- Mail Panel: inbox, sent, compose message, attachments
- News Feed and Forums: posts, comments, moderation flags

Possible Actions/Buttons:
- View Profile
- Send/Reply/Delete Message
- Post New Thread / Reply
- Refresh Feed
- Filter Leaderboards / Export Snapshot

Backend Requirements:
- Leaderboards endpoint
- Player profile endpoint
- Mail system CRUD endpoints
- News feed endpoint
- Forum CRUD endpoints
- Moderation tools and privacy controls
- Email/push notification integration

## Leaderboard and Profile Endpoints

### GET /api/v1/players/{id}/profile
Returns public details for a team and its owner user.
- **Access Rule:** Only accessible if the viewing user is in the same kingdom as the target user.
- **NPC Rule:** NPC teams do not have a public player profile (returns 404/not found).
- **Sensitive Data:** Excludes financial status (gold, essence, debt), and tactical metrics (chemistry, morale) to prevent match exploitation.
- **Response Format:**
  ```json
  {
    "user": {
      "id": 12,
      "display_name": "Username",
      "member_since": "2026-06-15T12:00:00Z"
    },
    "team": {
      "id": 4,
      "name": "Team Name",
      "emblem": "🛡️",
      "colors": { "primary": "#111", "secondary": "#222" },
      "fan_base": 1500,
      "reputation": 200,
      "combatant_count": 8,
      "trainer_count": 2
    },
    "league": {
      "tier_name": "T1",
      "group_name": "Group A",
      "position": 3,
      "points": 15,
      "played": 6,
      "wins": 5,
      "draws": 0,
      "losses": 1,
      "form": ["W", "W", "L", "W", "W"]
    },
    "is_own_profile": false,
    "can_message": true
  }
  ```

## UI Components
- **Player Profile Modal:** Implemented in `templates/components/community/player_profile_modal.html.twig`. It uses a shared Stimulus controller `player-profile` configured at the layout level in `game.html.twig` to dynamically fetch profile data and render it.
- **Compose Stack:** Contains a "Napsat zprávu" button which automatically dispatches `modal:open-compose` with the recipient prefilled and loads the mail compose dialog directly.

## Remaining sections to fill:
- Mail/forum APIs
- Moderation and privacy notes
- Implementation notes

