
## K čemu slouží celková Úroveň sídla (`totalLevel`)?

**Celková úroveň sídla** je **součet úrovní všech budov (facilities)** v základně (Headquarters). Počítá se v metodě [`processFacilityUpgradesTick`](../src/Service/Headquarters/HeadquartersService.php#L245-L271) pokaždé, když se dokončí upgrade některé budovy:

```php
// Recalculate HQ total level
$total = 0;
foreach ($hq->getFacilities() as $f) {
    $total += $f->getLevel();
}
$hq->setTotalLevel($total);
```

### Kde se zobrazuje?

Momentálně se `totalLevel` zobrazuje na **dvou místech**:

| Místo | Popis |
|---|---|
| [stats_card.html.twig](../templates/components/dashboard/stats_card.html.twig#L6) | Dashboard – statistický panel jako „HQ Level" |
| [compound_stats.html.twig](../templates/components/hq/compound_stats.html.twig#L8) | Stránka sídla – hlavní ukazatel „Úroveň sídla" |

### K čemu reálně slouží?

Aktuálně je `totalLevel` **čistě informační metrika** — ukazuje hráči celkový „progres" základny. **Neovlivňuje žádné herní mechaniky** (např. limity hrdinů, ceny upgradů, sílu výcviku apod.). Ty závisí vždy na úrovni konkrétní budovy, ne na celkovém součtu.

***