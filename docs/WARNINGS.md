## Analýza: Dokumentace vs. skutečný stav

### 🔴 Kritické — agent by selhal nebo udělal špatné rozhodnutí

**1. route-map.md odkazuje na neexistující controllery**

| Dokumentovaný controller | Skutečnost |
|---|---|
| `Web\TrainerController` | Neexistuje — trenéři jsou v `Web\TrainingController` |
| `Api\V1\TrainerController` | Neexistuje |
| `Web\NewsController`, `Web\WikiController` | Neexistují |

Agent, který by chtěl rozšířit správu trenérů, by hledal nebo vytvořil nesprávný soubor.

**2. route-map.md přiřazuje `POST /api/v1/heroes/{id}/train` do `Api\V1\HeroController`**

Ve skutečnosti trénink fronty obsluhuje `Api\V1\TrainingController` (`POST /api/v1/training-queue`). Cesta `/heroes/{id}/train` v `HeroController` buď neexistuje, nebo dělá něco jiného. Agent implementující trénink by šel do špatného controlleru.

**3. `SettingsController` má v kódu nekonzistentní pojmenování routy**

```php
// Web controller, ale route name "api_change_email"
#[Route('/app/settings/change-email', name: 'api_change_email', methods: ['POST'])]
```

Route name používá prefix `api_` uvnitř Web controlleru — matoucí pro každého agenta nebo vývojáře.

**4. training-system.md dokumentuje špatné API cesty**

```
GET /api/training-queue   ← v docs
GET /api/v1/training-queue ← skutečnost
```

Chybí `/v1/` ve všech referencích API v training-system.md.

---

### 🟡 Střední — agent by mohl jít špatným směrem

**5. Phase 5 (Combat) má otevřené blokátory z Phase 0 bez explicitního propojení**

roadmap.md Phase 0 stále obsahuje otevřené položky **#1** (combat formulas), **#8** (friendly matches), **#9** (arena match mechanics). Phase 5 entry je však nezmiňuje jako prerekvizity. Agent spouštějící Phase 5 si nemusí uvědomit, že je blokována nerozhořtými designovými rozhodnutími.

**6. `Service/Combat/` je prázdný adresář**

Battle.php existuje, ale Combat obsahuje jen `.gitkeep`. Roadmap říká "simulation engine pending", ale neuvádí, co přesně chybí. Agent by nevěděl, jestli začít od nuly nebo jestli existuje ještě něco jinde.

**7. combat-system.md má sekci "Simulation" jako explicitní placeholder**

Sekce říká doslova _"Sections still to fill"_ — ale tato skutečnost není zohledněna v known-issues.md. Jsou tam jen obecné body o formulích. Detailní architektura (worker/service, JSON struktura `combat_log`) nikde dokumentována není.

**8. `/app/settings` redirectuje na dashboard místo vykreslení stránky**

route-map.md říká: _"Profile & Settings page"_, ale controller obsahuje `return $this->redirectToRoute('app_dashboard')` — settings stránka není implementovaná, ale implementační status tabulka v README.md u Settings není vůbec zmíněna.

---

### 🟢 Nízká priorita — kosmetické nebo lokální nesoulady

**9. CHANGELOG.md má dva oddíly `### Changed` v jedné sekci `[Unreleased]`**

Porušuje vlastní dokumentovaný formát — `### Changed` se vyskytuje dvakrát za sebou.

**10. Implementační status tabulka v README.md chybí u Trainer System**

Trenéři jsou implementováni (entity `Trainer.php`, repozitář, integrováno do `TrainingController`), ale tabulka je nezmiňuje jako samostatnou oblast. Agent posuzující, co zbývá implementovat, by neměl přehled.

---

## Doporučené opravy

| Priorita | Soubor | Změna |
|---|---|---|
| 🔴 | route-map.md | Opravit sekci Trainer — controller `Web\TrainingController` místo `Web\TrainerController`; přidat poznámku, že `/heroes/{id}/train` neexistuje a trénink jde přes `/api/v1/training-queue` |
| 🔴 | training-system.md | Opravit API cesty na `/api/v1/...` |
| 🟡 | roadmap.md | V Phase 5 přidat explicitní odkaz na blokovací Phase 0 položky #1, #8, #9 |
| 🟡 | known-issues.md | Přidat položku: Combat Service architektura (Combat) není definována |
| 🟢 | CHANGELOG.md | Sloučit duplicitní `### Changed` bloky |
| 🟢 | README.md | Přidat řádek do implementační tabulky pro Trainer System a Settings |

Chcete, abych tyto opravy rovnou aplikoval?

