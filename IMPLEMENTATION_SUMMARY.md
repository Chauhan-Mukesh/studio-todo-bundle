# Implementation Summary

For full documentation, configuration, and installation instructions, please refer to the [README.md](README.md).

## Change Log

### Session 3 – 2026-04-02 (Copilot Agent)
- Live synchronization via Mercure SSE (`MercurePublisher` service + `useMercureSSE` hook)
- i18n / multi-language support (EN, DE, FR translation files in `src/Resources/translations/`)
- Studio UI frontend: Create Todo modal, SSE subscription, auth token injection
- Fixed `TodoController::update()` missing `$success` assignment
- Fixed `TodoRepository::count()` to use a clean query builder (ISSUE-031)
- Fixed `TodoRepository::createFilteredQuery()` complexity via private helper methods (ISSUE-063)
- Added `findCompletedBefore()`, `findSoftDeletedBefore()`, `batchSoftDelete()`, `batchUpdate()` (ISSUE-012/013)
- Fixed `TodoItem::fromArray()` date parsing with try/catch (ISSUE-018)
- Fixed `TodoManager::cleanup()` to use targeted repository query (ISSUE-012)
- Fixed `TodoManager::bulkDelete()` / `bulkUpdate()` to use single batch query (ISSUE-014)
- Cached `isAsyncEnabled()` result in constructor property (ISSUE-065)
- Dispatched `ASSIGNED`, `PRIORITY_CHANGED`, `STATUS_CHANGED` events (ISSUE-023/064)
- Fixed `Installer::uninstall()` to use Schema tools instead of raw SQL (ISSUE-036)
- Fixed `services.yaml` glob scope, removed `public: true` from non-controller services (ISSUE-034/057)
- Added `phpunit.xml.dist`, `.php-cs-fixer.php`, `.editorconfig` (ISSUE-058/059/060)
- Added `tsconfig.node.json`, ESLint config, vitest config for frontend (ISSUE-048/049/050)
- Removed redundant getters from `TodoOperationMessage` (ISSUE-035)
- Replaced generic `\Exception` catches with specific exception types (ISSUE-062)
- Replaced hardcoded color values with Ant Design theme tokens (ISSUE-052)
- Removed deprecated `antd/dist/reset.css` import (ISSUE-046)
- Removed raw user data from async messages (ISSUE-019)
- Updated `.gitignore` with current project structure (ISSUE-053)
- Added hard-delete API endpoint (ISSUE-071)
- Added `@return`, `@throws`, `@package` PHPDoc to key methods (ISSUE-038/039/040)
- Integration and unit tests added/fixed
