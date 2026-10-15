# Friendly Matches Screen

Reference: [friendly-match-system.md](../systems/friendly-match-system.md)

## Purpose

Kingdom-wide list of PvP friendly matches and challenge flow (slot picker, accept/decline/cancel).

## Route

- **Web:** `GET /app/friendly-matches` — `Web\FriendlyMatchController`
- **API:** under `/api/v1/arena/friendly*` and `/api/v1/friendly-matches/{id}/formation`

## UI

- Filter tabs: upcoming / pending invites / history
- Challenge form: opponent select + slot radio list from `GET .../slots`
- Row actions: accept / decline / cancel / open battle when completed
- Formation: reuse formation patterns via friendly-match formation API (link from scheduled row)

## Code map

| Layer | Path |
|-------|------|
| Web | `src/Controller/Web/FriendlyMatchController.php` |
| API | `src/Controller/Api/V1/ArenaController.php`, `FriendlyMatchFormationController.php` |
| Service | `src/Service/FriendlyMatch/` |
| Twig | `templates/friendly_matches/` |
| Stimulus | `assets/controllers/friendly_matches_controller.js` |
