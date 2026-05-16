# Player Profile & Settings Screen

Reference: [screens-overview.md](../screens-overview.md#19-player-profile--settings-screen)

Purpose: Per-screen API, events, UI data requirements, and implementation notes.

Displayed Information:
- Account Info: username, email, kingdom, member since, supporter tier
- Notification Settings: email/in-game toggles and frequency
- Privacy Settings: profile visibility, HQ visitor access, allow trade requests
- Language & Display settings and accessibility options

Possible Actions/Buttons:
- Edit Email
- Change Password (with verification)
- Update Notification Preferences
- Manage Connected Accounts
- Support & Donations
- Logout / Delete Account

Backend Requirements:
- User settings GET/PUT endpoints
- Email/password change with verification flows
- Privacy setting enforcement
- Connected accounts management endpoints

Sections to fill:
- Settings endpoints
- Privacy controls
- Email/password flows
- Implementation notes
