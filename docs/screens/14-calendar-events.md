# Calendar/Events Screen

Reference: [screens-overview.md](../screens-overview.md#14-calendarevents-screen)

Purpose: Per-screen API, events, UI data requirements, and implementation notes.

Displayed Information:
- Weekly Calendar Grid:
	- Scheduled server ticks and kingdom-level events
	- Player-scheduled matches and arena times
- Upcoming Events Panel:
	- Event list, rewards preview, participation requirements
- Active Events Panel:
	- Ongoing events with progress bars and time remaining
- Event History:
	- Completed events and earned rewards

Possible Actions/Buttons:
- View Event Details
- Participate in Event
- Set Reminder / Subscribe
- Filter Events (by type, rewards, participation)

Backend Requirements:
- Calendar events feed endpoint
- Server tick schedule and timezone normalization
- Event participation registration endpoint
- Notification/reminder system (push/email/in-app)
- Event history and audit logs

Sections to fill:
- Calendar API and event feed
- Tick schedule display
- Reminder/notification hooks
- Implementation notes
