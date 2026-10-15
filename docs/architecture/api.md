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

Every endpoint represents a resource or resource collection:

```
/api/v1/heroes              → Heroes collection
/api/v1/heroes/{id}         → Specific hero
/api/v1/heroes/{id}/train   → Hero training action
/api/v1/teams               → Teams collection
/api/v1/teams/{id}/battle   → Team battle action
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

**Action Response (POST to action endpoint):**
```json
{
  "data": {
    "heroId": "123",
    "action": "train",
    "resultLevel": 16,
    "experienceGained": 500,
    "costGold": 1200
  },
  "meta": {
    "timestamp": "2026-05-22T10:30:00Z"
  }
}
```

---

## Directory Structure

Organize controllers to clearly separate web and API layers:

```
src/
├── Controller/
│   ├── Web/
│   │   ├── HeroController.php          # /heroes routes (Twig rendering)
│   │   ├── TrainingController.php      # /training routes
│   │   ├── DashboardController.php
│   │   └── ...
│   ├── Api/
│   │   └── V1/
│   │       ├── HeroController.php      # /api/v1/heroes endpoints
│   │       ├── TrainingController.php  # /api/v1/training endpoints
│   │       ├── BattleController.php    # /api/v1/battles endpoints
│   │       └── ...
│   └── HealthCheckController.php       # /health (shared)
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
# config/routes.yaml

# Web Routes
web:
  path: /
  resource: '../src/Controller/Web/'
  type: annotation

# Internal API Routes (v1)
api_v1:
  path: /api/v1
  resource: '../src/Controller/Api/V1/'
  type: annotation
  prefix: /api/v1
```

**Controller Examples:**

```php
// src/Controller/Web/HeroController.php
#[Route('/heroes', name: 'hero_list')]
public function list(): Response { ... }  // Renders Twig

#[Route('/heroes/{id}', name: 'hero_detail')]
public function detail(int $id): Response { ... }  // Renders Twig
```

```php
// src/Controller/Api/V1/HeroController.php
#[Route('', name: 'api_heroes_list', methods: ['GET'])]
public function list(): JsonResponse { ... }  // Returns JSON

#[Route('/{id}', name: 'api_heroes_get', methods: ['GET'])]
public function get(int $id): JsonResponse { ... }  // Returns JSON

#[Route('/{id}/train', name: 'api_heroes_train', methods: ['POST'])]
public function train(int $id, Request $request): JsonResponse { ... }  // Action endpoint
```

---

## Service Layer (Shared Logic)

Services contain all business logic and are consumed by both Web and API controllers:

```php
// src/Service/HeroService.php
class HeroService
{
    public function __construct(
        private HeroRepository $heroRepository,
        private TrainingService $trainingService,
    ) {}

    public function getHero(int $id): Hero
    {
        return $this->heroRepository->find($id)
            ?? throw new ResourceNotFoundException("Hero not found");
    }

    public function trainHero(int $id, int $experiencePoints): TrainingResult
    {
        $hero = $this->getHero($id);

        // Validate training conditions
        if ($hero->isFatigued()) {
            throw new ValidationException("Hero is too fatigued to train");
        }

        // Apply training
        return $this->trainingService->applyTraining($hero, $experiencePoints);
    }

    public function listHeroes(int $page = 1, int $limit = 20): PaginatedResult
    {
        return $this->heroRepository->findPaginated($page, $limit);
    }
}
```

**Usage in Web Controller:**
```php
// src/Controller/Web/HeroController.php
public function __construct(private HeroService $heroService) {}

#[Route('/heroes/{id}', name: 'hero_detail')]
public function detail(int $id): Response
{
    $hero = $this->heroService->getHero($id);
    return $this->render('hero/detail.html.twig', ['hero' => $hero]);
}
```

**Usage in API Controller:**
```php
// src/Controller/Api/V1/HeroController.php
public function __construct(private HeroService $heroService) {}

#[Route('/{id}', name: 'api_heroes_get', methods: ['GET'])]
public function get(int $id): JsonResponse
{
    $hero = $this->heroService->getHero($id);
    return $this->json(['data' => $this->serializer->serialize($hero)]);
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

## Example Endpoint: Hero Training

### Web Flow (Current)

```
User clicks "Train" button in Twig template
  ↓
Stimulus component captures click
  ↓
AJAX POST to /api/v1/heroes/123/train
  ↓
API Controller validates and calls HeroService::trainHero()
  ↓
Service applies training logic, returns result
  ↓
API Controller returns JSON: { data: { heroId, resultLevel, costGold } }
  ↓
Stimulus updates DOM with response
```

### API Controller

```php
// src/Controller/Api/V1/HeroController.php
#[Route('/{heroId}/train', name: 'api_heroes_train', methods: ['POST'])]
public function train(
    int $heroId,
    Request $request,
    HeroService $heroService,
    TrainingService $trainingService,
): JsonResponse {
    try {
        $data = json_decode($request->getContent(), true);
        $experiencePoints = $data['experiencePoints'] ?? 100;

        $result = $heroService->trainHero($heroId, $experiencePoints);

        return $this->json(
            [
                'data' => [
                    'heroId' => $heroId,
                    'resultLevel' => $result->getNewLevel(),
                    'experienceGained' => $result->getExperienceGained(),
                    'costGold' => $result->getCostGold(),
                ]
            ],
            Response::HTTP_OK
        );
    } catch (ValidationException $e) {
        return $this->json(
            ['error' => ['code' => 'VALIDATION_ERROR', 'message' => $e->getMessage()]],
            Response::HTTP_422
        );
    } catch (ResourceNotFoundException $e) {
        return $this->json(
            ['error' => ['code' => 'NOT_FOUND', 'message' => $e->getMessage()]],
            Response::HTTP_404
        );
    }
}
```

### Stimulus Component

```javascript
// assets/controllers/hero_trainer_controller.js
import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
  static targets = ['button', 'resultMessage'];

  train(event) {
    event.preventDefault();
    const heroId = this.element.dataset.heroId;

    fetch(`/api/v1/heroes/${heroId}/train`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ experiencePoints: 100 })
    })
    .then(response => response.json())
    .then(data => {
      if (data.error) {
        this.resultMessageTarget.textContent = `Error: ${data.error.message}`;
      } else {
        this.resultMessageTarget.textContent =
          `Success! Hero reached level ${data.data.resultLevel}. Cost: ${data.data.costGold} gold.`;
      }
    });
  }
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
// tests/Service/HeroServiceTest.php
class HeroServiceTest extends TestCase
{
    public function testTrainHeroIncrementsLevel(): void
    {
        $hero = new Hero();
        $hero->setLevel(10);

        $result = $this->heroService->trainHero($hero->getId(), 1000);

        $this->assertGreaterThan(10, $result->getNewLevel());
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
        $this->assertJsonStringEqualsJsonString(
            json_encode(['data' => ['id' => 1, 'name' => 'Aragorn']]),
            $client->getResponse()->getContent()
        );
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

- [REST API Best Practices](https://restfulapi.net/)
- [HTTP Status Codes](https://developer.mozilla.org/en-US/docs/Web/HTTP/Status)
- [JSON API Specification](https://jsonapi.org/)
- [Symfony Symfony Controllers](https://symfony.com/doc/current/controller.html)
