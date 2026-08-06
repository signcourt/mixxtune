# Backstage Distribution V2 Master Blueprint

## Panels

1. Super Admin
2. Admin
3. Label
4. Artist

## Shared Architecture

All common functionality must be implemented once and reused by every panel.

### Common modules

- Dashboard
- Releases
- Tracks
- Contributors
- Stores
- Catalogue
- Reports
- Royalties
- Wallet
- Withdrawals
- Statements
- Invoices
- KYC
- Support
- Notifications
- Settings

## Role-based behaviour

### Super Admin
Full system access.

### Admin
Access limited to assigned labels and artists.

### Label
Access limited to its own label, artists and catalogue.

### Artist
Access limited to its own profile, releases and financial information.

## Release workflow

Draft → Submitted → Changes Requested / Approved / Rejected  
Approved → Processing → Delivered → Live  
Live → Takedown Requested → Taken Down

## Shared frontend pattern

resources/js/V2/Shared/
- Layouts
- Components
- Dashboard
- Releases
- Tracks
- Stores
- Reports
- Finance
- Profile

## Backend pattern

app/V2/
- Actions
- Services
- Policies
- DTOs
- Support

Controllers remain panel-aware, while business logic stays inside shared services.

## Installer rules

Every module installer must:

1. Create a safety backup.
2. Verify dependencies.
3. Copy or update files idempotently.
4. Run syntax checks.
5. Run migrations.
6. Build frontend assets.
7. Clear caches.
8. Verify routes.
9. Save installation state.
10. Stop and rollback on failure.
