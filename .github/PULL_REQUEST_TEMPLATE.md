## Summary

(Short description of the change)

## Checklist

- [ ] Code changes are covered by tests where applicable
- [ ] I added/updated necessary documentation (`route-map.md`, `entity-reference.md`, systems/screens docs as needed)
- [ ] **UI changes:** Twig uses semantic classes only (`hidden` / `sr-only` / `group` exceptions)
- [ ] **Backend changes:** business logic in `Service/`; translation keys in EN + CS YAML; routes in `route-map.md`

## Verification (human / CI — not required in the PR description)

Run locally or rely on CI before merge:

```bash
bash scripts/check-ui-compliance.sh   # UI changes
bash scripts/check-backend-docs.sh    # routes + entities in docs
docker exec -u apache fantager-web composer test
docker exec -u apache fantager-web composer phpstan
docker exec -u apache fantager-web composer php-cs
```

## Changelog

`CHANGELOG.md` is parked in [`_deferred/`](../_deferred/) until the project reaches a stable version — no changelog entry required for now.

## Related issues

-

