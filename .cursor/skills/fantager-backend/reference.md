# Fantager Backend — Reference

Quick map of production PHP layout. Full workflow: [SKILL.md](SKILL.md) · [examples.md](examples.md).

---

## Controller map

| Namespace | Path prefix | Role |
|-----------|-------------|------|
| `App\Controller\Web` | `/`, `/app/*`, auth paths | Twig pages, flashes, redirects |
| `App\Controller\Api\V1` | `/api/v1/*` | JSON for Stimulus/AJAX |

**Traits:** `App\Controller\Api\ApiControllerTrait` — `jsonError`, `jsonException`, `transMessage`

**Copy patterns:** `SummoningController` (API), `HeroController` (Web), `TrainingController` (class prefix + subpaths)

---

## Service domains

| Directory | Main services | Domain doc |
|-----------|---------------|------------|
| `Auth/` | `RegistrationService`, `UserSettingsService`, `PasswordResetService` | [auth-system.md](../../docs/systems/auth-system.md) |
| `Kingdom/` | `KingdomService`, `KingdomInitializationService` | [kingdom-system.md](../../docs/systems/kingdom-system.md) |
| `Team/` | `TeamService`, `TeamRosterService`, `FanClubService` | [team-system.md](../../docs/systems/team-system.md) |
| `TeamChronicle/` | `TeamChronicleService`, `TeamChroniclePresenter` | [team-chronicle-system.md](../../docs/systems/team-chronicle-system.md) |
| `Hero/` | `HeroService`, `HeroGenerator`, `HeroDismissalService` | [hero-system.md](../../docs/systems/hero-system.md) |
| `Summoning/` | `SummoningService` | [summoning-system.md](../../docs/systems/summoning-system.md) |
| `Economy/` | `EconomyService`, `FinancialCrisisService`, `RoyalTreasuryService`, `ArenaRevenueService` | [economy-system.md](../../docs/systems/economy-system.md) |
| `Headquarters/` | `HeadquartersService`, `ArenaService`, `HqMaintenanceCalculator` | [headquarters-system.md](../../docs/systems/headquarters-system.md) |
| `Training/` | `TrainingService`, `TrainerDismissalService` | [training-system.md](../../docs/systems/training-system.md) |
| `Item/` | `ItemService` | [item-system.md](../../docs/systems/item-system.md) |
| `Spell/` | `SpellService` | [spell-system.md](../../docs/systems/spell-system.md) |
| `Formation/` | `FormationService`, `FixtureFormationService` | [formation-system.md](../../docs/systems/formation-system.md) |
| `Marketplace/` | `MarketplaceService` | [marketplace-system.md](../../docs/systems/marketplace-system.md) |
| `Community/` | `CommunityService`, `ForumThreadHelper`, `ContentFilterService` | [community-system.md](../../docs/systems/community-system.md) |
| `Calendar/` | `CalendarService`, `TickScheduleCalculator`, `KingdomTickRunnerService`, `TickClock` | [calendar-system.md](../../docs/systems/calendar-system.md) |
| `League/` | `LeagueService`, `LeagueFixtureScheduler`, `SeasonTransitionService` | [league-system.md](../../docs/systems/league-system.md) |
| `Graveyard/` | `GraveyardService`, `GraveyardPresenter` | [graveyard-system.md](../../docs/systems/graveyard-system.md) |
| `Notification/` | `NotificationService`, `NotificationHelper` | [notification-system.md](../../docs/systems/notification-system.md) |
| `Config/` | `RaceConfig`, `KingdomInitConfig`, `StatusEffectConfig` | [entity-reference.md](../../docs/entity-reference.md) |
| `Translation/` | `UserMessageTranslator` | — |

**Handlers:** `src/Message/ProcessKingdomTicksHandler.php` — weekly tick orchestration  
**Commands:** `src/Command/` — `ProcessTicksCommand`, `InitializeKingdomCommand`, …

---

## Entity & repository layout

| Domain folder | Entities | Repositories |
|---------------|----------|--------------|
| `Auth/` | `User`, `UserSettings`, `VerificationToken` | `UserRepository`, … |
| `Team/` | `Team`, `FinancialRecord`, `TeamChronicle`, `TeamSummonHistory` | matching `*Repository` |
| `Hero/` | `Hero`, `HeroTrainingHistory`, `HeroSpell`, `SchoolMastery` | matching `*Repository` |
| `Kingdom/` | `Kingdom`, `KingdomTickLog` | matching `*Repository` |
| `Headquarters/` | `Headquarters`, `Facility` | matching `*Repository` |
| `Formation/` | `Formation`, `FormationSlot` | matching `*Repository` |
| `Item/` | `Item` | `ItemRepository` |
| `Spell/` | `Spell` | `SpellRepository` |
| `Marketplace/` | `MarketplaceListing`, `MarketplaceBid`, `MarketplaceTransaction` | matching `*Repository` |
| `League/` | `LeagueSeason`, `LeagueTier`, `LeagueGroup`, `LeagueStanding`, `LeagueFixture` | matching `*Repository` |
| `Community/` | `ForumThread`, `ForumPost`, `Message`, `NewsArticle` | matching `*Repository` |
| `Graveyard/` | `GraveyardMemorial` | `GraveyardMemorialRepository` |
| `Notification/` | `Notification` | `NotificationRepository` |
| `Combat/` | `Battle` *(scaffold)* | `BattleRepository` |

Enums: `src/Enum/` — backed enums for statuses, types, races (see entity-reference).

---

## Tests mirror map

| Production | Tests |
|------------|-------|
| `src/Service/{Domain}/*Service.php` | `tests/Service/{Domain}/*Test.php` |
| `src/Controller/Web/*` | `tests/Controller/Web/*Test.php` (selected) |
| `src/Command/*` | `tests/Command/*Test.php` |

**Pattern:** PHPUnit `TestCase` + mocked dependencies; `#[AllowMockObjectsWithoutExpectations]` when using PHPUnit 11 mocks.

---

## i18n & user messages

| File | Purpose |
|------|---------|
| `translations/messages.en.yaml` | User-facing strings (EN) |
| `translations/messages.cs.yaml` | User-facing strings (CS) |
| `translations/validators.*.yaml` | Form validation messages |

**PHP:** `UserFacingException('error.key', $params)` · `UserMessageTranslator` · API `ApiControllerTrait::transMessage`  
**Twig:** `{{ 'key'|trans }}` · **Stimulus:** `data-*-value` from Twig

---

## Config & static game data

| Path | Loaded by |
|------|-----------|
| `config/game/races.yaml` | `RaceConfig` |
| `config/game/kingdoms/` | `KingdomInitConfig` |
| `config/packages/*.yaml` | Symfony |

---

## Docs to update when changing…

| Change | Update |
|--------|--------|
| New route | `docs/route-map.md` |
| New entity/table | `docs/entity-reference.md` |
| New enum | `docs/entity-reference.md` § Enums |
| New domain behavior | `docs/systems/{domain}-system.md` |
| New screen | `docs/screens/NN-*.md`, `docs/screen-code-map.md` |
| Feature shipped / status | `docs/README.md` § Current Implementation Status |

**Automated check (human / CI):** `bash scripts/check-backend-docs.sh` (routes, entities, enums, PHP → YAML keys)

---

## Exceptions

| Class | Use |
|-------|-----|
| `App\Exception\UserFacingException` | Expected business failures with translation key + params |
| `\DomainException` / `\InvalidArgumentException` | Message = translation key when caught by `jsonException` |

---

## Related frontend (vertical slice)

| Backend area | Twig templates | Stimulus (examples) |
|--------------|----------------|------------------------|
| Summoning | `templates/summoning/` | `summoning_controller.js` |
| Training | `templates/training/` | `training_controller.js` |
| Marketplace | `templates/marketplace/` | marketplace controllers |
| Formation | `templates/formation/` | `formation_controller.js` |
| HQ | `templates/headquarters/` | `hq_controller.js` |

See [fantager-ui/reference.md](../fantager-ui/reference.md) for SCSS/Twig class map.
