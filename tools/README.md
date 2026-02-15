# Tools

## Regression snapshots (SAGA JSON + totals)

This tool records and checks snapshots for critical package outputs without changing app behavior.

### Record

```
php tools/regression_snapshots.php record 10091 10037
```

Snapshots are written to:

```
tools/snapshots/package_<id>.json
```

### Check

```
php tools/regression_snapshots.php check 10091 10037
```

The check compares normalized JSON (stable key order, types preserved) and exits with code 1 on mismatch.

## Audit Log

Tail recent audit events:

```
php tools/audit_tail.php 50
```

Outputs: `created_at | user:<id> | action | entity_type/entity_id`.
