# Internal API Design Guide

## Purpose

This document defines the design principles and structure for internal API endpoints. While the frontend currently uses server-rendered Twig templates with Stimulus and Turbo, internal API routes are built and maintained as first-class resources. This approach:

- Enables future REST API consumption (mobile apps, external clients)
- Provides clear reference implementations for new feature development
- Maintains clean separation between business logic and presentation
- Prepares the codebase for seamless API-first or backend-for-frontend (BFF) evolution

## Overview

### What is the Internal API?

The Internal API consists of JSON endpoints that:
- Represent game resources (heroes, training, battles, etc.)
- Follow REST principles (resources, HTTP methods, status codes)
- Return structured JSON responses
- Are consumed by frontend Stimulus components via AJAX
- Are **not exposed publicly** in the current phase but are fully functional

### Current Usage

- **Web Controllers** → Render Twig templates for navigation and server-rendered pages
- **Stimulus Components** → Trigger AJAX calls to Internal API routes
- **Turbo** → Handles navigation and partial page updates

### Future Usage

The Internal API can evolve into:
- Public REST API (require authentication, add versioning)
- Mobile app backend
- Third-party integrations
- Backend-for-Frontend (BFF) pattern for future single-page applications

---

## REST Principles for Internal API

### Resource-Oriented Design

Every endpoint represents a resource or resource collection. Canonical live list: [architecture/routes.md](routes.md). Authenticated Twig pages use the `/app/…` prefix (e.g. `GET /app/heroes`). There is no `POST /api/v1/heroes/{id}/train` — training is trainer assign / unassign / configure / promote.

```
/api/v1/heroes                            → Heroes collection
/api/v1/heroes/{id}                       → Specific hero
/api/v1/training/trainers                 → Team trainers
/api/v1/training/trainers/{id}/assign     → Assign a hero to a trainer
```

### HTTP Methods

| Method | Purpose | Idempotent |
|--------|---------|-----------|
| GET    | Retrieve resource(s) | ✓ |
| POST   | Create resource or trigger action | ✗ |
| PUT    | Replace entire resource | ✓ |
| PATCH  | Partial update | ✓ |
| DELETE | Remove resource | ✓ |

### HTTP Status Codes

| Code | Meaning | Use Case |
|------|---------|----------|
| 200  | OK | Successful GET or action completion |
| 201  | Created | Successful POST creating a resource |
| 204  | No Content | Successful DELETE or action with no response body |
| 400  | Bad Request | Invalid input or validation failure |
| 401  | Unauthorized | Missing or invalid authentication |
| 403  | Forbidden | Authenticated but lacks permission |
| 404  | Not Found | Resource does not exist |
| 409  | Conflict | Business rule violation (e.g., insufficient resources) |
| 422  | Unprocessable Entity | Validation errors with details |
| 500  | Internal Server Error | Unexpected error |

### Response Format

**Success Response (GET):**
```json
{
  "data": {
    "id": "123",
    "name": "Aragorn",
    "level": 15,
    "race": "Human",
    "experience": 5200
  },
  "meta": {
    "timestamp": "2026-05-22T10:30:00Z"
  }
}
```

**Collection Response:**
```json
{
  "data": [
    { "id": "1", "name": "Hero 1", ... },
    { "id": "2", "name": "Hero 2", ... }
  ],
  "meta": {
    "total": 42,
    "page": 1,
    "limit": 20,
    "timestamp": "2026-05-22T10:30:00Z"
  }
}
```

**Error Response:**
```json
{
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "Validation failed",
    "details": [
      { "field": "name", "message": "Name is required" },
      { "field": "level", "message": "Level must be between 1 and 100" }
    ],
    "timestamp": "2026-05-22T10:30:00Z"
  }
}
```

**Action Response (POST to action endpoint):** live endpoints typically return a **flat snake_case object** from `$this->json()`, not a required `data` wrapper. Errors are `{ "error": "translated message" }` via `ApiControllerTrait::jsonError` / `jsonException`.

```json
{
  "message": "Hero assigned to trainer.",
  "hero_id": 123,
  "trainer_id": 45
}
```

---

## Directory Structure

Organize controllers to clearly separate web and API layers:

```
src/
├── Controller/
│   ├── Web/
│   │   ├── HeroController.php          # /app/heroes (Twig)
│   │   ├── TrainingController.php      # /app/training (Twig)
│   │   ├── DashboardController.php
│   │   └── ...
│   ├── Api/
│   │   └── V1/
│   │       ├── HeroController.php      # /api/v1/heroes
│   │       ├── TrainingController.php  # /api/v1/training/trainers
│   │       ├── BattleController.php    # /api/v1/battles
│   │       └── ...
│   └── HealthCheckController.php       # GET /health (liveness JSON, A8.3)
├── Service/
│   ├── HeroService.php                 # Business logic (Web & API share this)
│   ├── TrainingService.php
│   ├── BattleService.php
│   ├── ValidationService.php
│   └── ...
├── Repository/
│   ├── HeroRepository.php
│   ├── TrainingRepository.php
│   └── ...
├── Entity/
│   ├── Hero.php
│   ├── Training.php
│   ├── Team.php
│   └── ...
├── DTO/
│   ├── HeroDTO.php                     # Data Transfer Objects for API responses
│   ├── TrainingDTO.php
│   └── ...
├── Exception/
│   ├── ValidationException.php
│   ├── ResourceNotFoundException.php
│   └── ...
└── Serializer/
    ├── HeroSerializer.php              # Convert entities to DTOs/JSON
    ├── ErrorSerializer.php
    └── ...
```

---

## Routing Configuration

**Routing Convention:**

Web routes and API routes coexist in the same routing configuration. Use prefixes and controller namespacing to organize them:

```yaml
# config/routes.yaml — attribute discovery; API controllers put /api/v1 on the class Route

controllers_web:
  resource: ../src/Controller/Web/
  type: attribute

controllers_api:
  resource: ../src/Controller/Api/V1/
  type: attribute
```

**Controller Examples:**

```php
// src/Controller/Web/HeroController.php
#[Route('/app/heroes', name: 'app_heroes', methods: ['GET'])]
public function roster(): Response { ... }  // Renders Twig

#[Route('/app/heroes/{id}', name: 'app_heroes_detail', requirements: ['id' => '\d+'], methods: ['GET'])]
public function detail(int $id, Request $request): Response { ... }  // Renders Twig
```

```php
// src/Controller/Api/V1/HeroController.php
#[Route('/api/v1/heroes')]
class HeroController extends AbstractController
{
    #[Route('', name: 'api_heroes_list', methods: ['GET'])]
    public function list(): JsonResponse { ... }

    #[Route('/{id}', name: 'api_heroes_detail', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function detail(int $id): JsonResponse { ... }
}

// Training actions live on TrainingController, not HeroController:
// POST /api/v1/training/trainers/{id}/assign
```

---

## Service Layer (Shared Logic)

Services contain all business logic and are consumed by both Web and API controllers:

```php
// src/Service/Training/TrainingService.php
class TrainingService
{
    public function assignHero(Hero $trainer, Hero $hero, Team $team, \DateTimeImmutable $now): void
    {
        // Ownership, lock window, slot limit, Available status — throw UserFacingException on violation
        // Persist trainer ↔ trainee assignment
    }
}
```

**Usage in Web Controller:**
```php
// src/Controller/Web/HeroController.php
#[Route('/app/heroes/{id}', name: 'app_heroes_detail', requirements: ['id' => '\d+'], methods: ['GET'])]
public function detail(int $id, Request $request): Response
{
    $hero = $this->heroRepository->find($id);
    // Guard team ownership, then render Twig
    return $this->render('hero/detail.html.twig', ['hero' => $hero]);
}
```

**Usage in API Controller:**
```php
// src/Controller/Api/V1/HeroController.php
#[Route('/{id}', name: 'api_heroes_detail', methods: ['GET'], requirements: ['id' => '\d+'])]
public function detail(int $id): JsonResponse
{
    $hero = $this->heroService->findForTeam($id, $this->getPlayerTeam());
    if (null === $hero) {
        return $this->jsonError('error.hero_not_found', 404);
    }

    return $this->json($this->heroService->serialize($hero));
}
```

---

## DTO & Serialization

Use Data Transfer Objects (DTOs) to normalize responses and decouple entities from API payloads:

```php
// src/DTO/HeroDTO.php
readonly class HeroDTO
{
    public function __construct(
        public int $id,
        public string $name,
        public int $level,
        public string $race,
        public int $experience,
    ) {}
}

// src/Serializer/HeroSerializer.php
class HeroSerializer
{
    public function toDTO(Hero $hero): HeroDTO
    {
        return new HeroDTO(
            $hero->getId(),
            $hero->getName(),
            $hero->getLevel(),
            $hero->getRace(),
            $hero->getExperience(),
        );
    }

    public function toArray(Hero $hero): array
    {
        $dto = $this->toDTO($hero);
        return [
            'id' => $dto->id,
            'name' => $dto->name,
            'level' => $dto->level,
            'race' => $dto->race,
            'experience' => $dto->experience,
        ];
    }
}
```

---

## Example Endpoint: Assign Hero to Trainer

Live page: `GET /app/training`. Live action: `POST /api/v1/training/trainers/{id}/assign` with body `{ "hero_id": N }`. Weekly attribute ticks apply the assignment; this POST does not grant XP or change displayed level ([product/known-issues.md](../product/known-issues.md) #6).

### Web Flow (Current)

```
User assigns a hero on /app/training
  ↓
Stimulus (training_controller) captures the change
  ↓
AJAX POST to /api/v1/training/trainers/{id}/assign  body: { "hero_id": 123 }
  ↓
Api\V1\TrainingController validates team/trainer/hero and calls TrainingService::assignHero()
  ↓
Service enforces lock window, slots, and Available status
  ↓
API returns JSON: { message, hero_id, trainer_id }
  ↓
Stimulus shows a translated alert and reloads
```

### API Controller

```php
// src/Controller/Api/V1/TrainingController.php
#[Route('/training/trainers/{id}/assign', name: 'api_training_trainer_assign', methods: ['POST'], requirements: ['id' => '\d+'])]
public function assign(int $id, Request $request): JsonResponse
{
    $team = $this->getPlayerTeam();
    if (null === $team) {
        return $this->jsonError('error.no_team', 422);
    }

    $trainer = $this->findTeamTrainer($id, $team);
    if (null === $trainer) {
        return $this->jsonError('error.trainer_not_found', 404);
    }

    $body = json_decode($request->getContent(), true) ?? [];
    $heroId = (int) ($body['hero_id'] ?? 0);
    $hero = $this->heroRepository->findOneBy(['id' => $heroId, 'team' => $team, 'role' => HeroRole::Combatant]);
    if (null === $hero) {
        return $this->jsonError('error.hero_not_found', 404);
    }

    try {
        $this->trainingService->assignHero($trainer, $hero, $team, new \DateTimeImmutable());
    } catch (\DomainException $e) {
        return $this->jsonException($e, 422);
    }

    return $this->json([
        'message' => $this->transMessage('error.training_success_assign'),
        'hero_id' => $hero->getId(),
        'trainer_id' => $trainer->getId(),
    ]);
}
```

### Stimulus Component

User-facing copy comes from Stimulus values (`data-*-value` in Twig), not hardcoded strings.

```javascript
// assets/controllers/training_controller.js (excerpt)
async assignTrainee(event) {
  const trainerId = event.currentTarget.dataset.trainerId;
  const heroId = event.currentTarget.value;
  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

  const response = await fetch(`/api/v1/training/trainers/${trainerId}/assign`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-Token': csrfToken,
    },
    body: JSON.stringify({ hero_id: parseInt(heroId, 10) }),
  });
  const result = await response.json();
  if (!response.ok || result.error) {
    throw new Error(result.error || this.errorAssignHeroValue);
  }
  this.showAlert('success', this.successAssignValue);
}
```

---

## Error Handling

**Consistent Error Structure:**

All API endpoints return errors in a standardized format:

```php
// src/Exception/ApiException.php (base)
abstract class ApiException extends Exception
{
    abstract public function getErrorCode(): string;
    abstract public function getHttpStatusCode(): int;
}

// src/Exception/ValidationException.php
class ValidationException extends ApiException
{
    public function __construct(
        private array $errors,
        string $message = "Validation failed"
    ) {
        parent::__construct($message);
    }

    public function getErrorCode(): string { return 'VALIDATION_ERROR'; }
    public function getHttpStatusCode(): int { return Response::HTTP_422; }
    public function getErrors(): array { return $this->errors; }
}

// src/Exception/ResourceNotFoundException.php
class ResourceNotFoundException extends ApiException
{
    public function getErrorCode(): string { return 'NOT_FOUND'; }
    public function getHttpStatusCode(): int { return Response::HTTP_404; }
}
```

**Exception Event Listener:**

```php
// src/EventListener/ApiExceptionListener.php
#[AsEventListener(event: ExceptionEvent::class, priority: 100)]
class ApiExceptionListener
{
    public function __invoke(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();
        $request = $event->getRequest();

        // Only handle API requests
        if (!str_starts_with($request->getPathInfo(), '/api/')) {
            return;
        }

        if ($exception instanceof ApiException) {
            $event->setResponse(new JsonResponse(
                [
                    'error' => [
                        'code' => $exception->getErrorCode(),
                        'message' => $exception->getMessage(),
                        'details' => $exception instanceof ValidationException
                            ? $exception->getErrors()
                            : null,
                    ]
                ],
                $exception->getHttpStatusCode()
            ));
        }
    }
}
```

---

## Authentication & Authorization

API endpoints should be protected. Currently they are internal only, but design with security in mind:

```php
// src/Controller/Api/V1/HeroController.php
#[Route('/{id}', methods: ['GET'])]
public function get(int $id): JsonResponse
{
    // Verify user is authenticated and owns the hero
    $user = $this->getUser() ?? throw new UnauthenticatedException();
    $hero = $this->heroService->getHero($id);

    if ($hero->getOwner() !== $user) {
        throw new ForbiddenException();
    }

    return $this->json(['data' => $this->serializer->toArray($hero)]);
}
```

---

## Versioning Strategy

As the API grows, use version prefixes in routes:

```
/api/v1/heroes         # Version 1
/api/v2/heroes         # Version 2 (if needed)
```

Each version lives in its own controller directory:
```
src/Controller/Api/V1/
src/Controller/Api/V2/
```

---

## Testing Internal API

Services and API controllers are fully testable:

```php
// tests/Service/Training/TrainingServiceTest.php
class TrainingServiceTest extends TestCase
{
    public function testAssignHeroLinksCombatantToTrainer(): void
    {
        $this->trainingService->assignHero($trainer, $hero, $team, new \DateTimeImmutable());

        $this->assertSame($trainer, $hero->getTrainer());
    }
}

// tests/Controller/Api/V1/HeroControllerTest.php
class HeroControllerTest extends WebTestCase
{
    public function testGetHeroReturnsJson(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/v1/heroes/1');

        $this->assertResponseIsSuccessful();
        $this->assertResponseFormatSame('json');
    }
}
```

---

## Future Evolution

### Phase 1 (Current): Internal API Only
- API endpoints exist but are not publicly exposed
- Frontend uses Stimulus + Turbo with AJAX calls to internal endpoints
- Services contain all business logic

### Phase 2: Public REST API
- Add authentication (JWT, OAuth, API keys)
- Document endpoints with OpenAPI/Swagger
- Rate limiting and logging
- Versioning strategy becomes important

### Phase 3: Mobile or SPA
- Mobile apps consume the REST API
- Single-page application migrates to consume API instead of Twig

### Phase 4: Microservices (if needed)
- Internal APIs become independent services
- Service-to-service communication

---

## References

- [architecture/routes.md](routes.md) — live Web + API path list
- [REST API Best Practices](https://restfulapi.net/)
- [HTTP Status Codes](https://developer.mozilla.org/en-US/docs/Web/HTTP/Status)
- [JSON API Specification](https://jsonapi.org/)
- [Symfony Controllers](https://symfony.com/doc/current/controller.html)
