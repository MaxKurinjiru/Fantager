# Community System

Reference: [game-summary.md](../game-summary.md#214-community-system)

Purpose: Document leaderboards, mail, forums, and social features.

Sections to fill:
- Data models for messages, posts, leaderboards
- Privacy and moderation rules
- Notifications and mail delivery
- Forum threading and search
- Integration points and APIs
- Implementation notes


Summary:
- Community includes leaderboards, player profiles, mail, news feed, and forums. Leaderboards provide rankings for various metrics; mail supports inbox/sent; forums provide threaded discussions.

APIs:
- GET /api/leaderboards
- GET/POST /api/messages
- Forum CRUD endpoints

