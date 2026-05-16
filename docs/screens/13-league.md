# League Screen

Reference: [screens-overview.md](../screens-overview.md#13-league-screen)

Purpose: Per-screen API, events, UI data requirements, and implementation notes.

Displayed Information:
- League Standings:
	- Current season, player's tier/group/rank, points
	- Promotion/relegation indicators and season timer
	- Rank change indicators (up/down)
- Group Standings Table:
	- Rank, team name, played, wins/draws/losses, points, recent form
- Fixtures:
	- Upcoming and past matches with times, status, and opponents
- Seasonal Rewards Preview:
	- Rewards for current rank and claimable status

Possible Actions/Buttons:
- View Match Details
- Prepare for Match
- View Opponent Profile
- Switch Group/Tier View
- View Season History
- Claim Seasonal Rewards

Backend Requirements:
- Standings/leaderboard endpoints
- Fixture scheduling APIs
- Season management jobs (promotion/relegation)
- Rewards distribution and claim endpoints

Sections to fill:
- Standings endpoints
- Fixture APIs
- Season management hooks
- Implementation notes
