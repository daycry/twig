# Performance, Warmup & Operational Guide

This guide focuses on how to keep Twig rendering fast in CodeIgniter using the features provided by this integration: warmup, discovery caching, dynamic registry, and targeted invalidations.

---
## 1. Performance Pillars
1. Avoid unnecessary filesystem scans (Discovery Cache + Snapshot)
2. Avoid just-in-time compilation on first user traffic (Warmup)
3. Minimize recompilation churn (Smart Invalidations)
4. Avoid redundant environment rebuilds (Dynamic Registry lifecycle)
5. Monitor & react (Diagnostics)

---
## 2. Warmup Strategy
Warmup pre-compiles template PHP classes so first real user request is cache-hit.

API:
```php
$twig->warmup(['emails/welcome','layout/base']);
$twig->warmupAll();            // scan discovery list and compile all
$twig->warmup(['emails/welcome'], true); // force recompile even if index says compiled
```
CLI:
```
php spark twig:warmup --all
php spark twig:warmup welcome emails/reset-password
php spark twig:warmup welcome --force
```
Return shape:
```php
['compiled' => X, 'skipped' => Y, 'errors' => Z]
```

Recommendations:
| Environment | Action |
|-------------|--------|
| Production deployment | `warmup --all` after code release |
| Staging smoke test | Warm critical templates only |
| Local dev | Usually skip; rely on live recompilation |

When to use `--force` / `true` force flag:
- After manual deletion of compiled files without clearing index
- After changing dynamic extensions that affect generated code
- For benchmarking compile cost

---
## 3. Discovery Optimization
The discovery phase enumerates logical template names and can dominate cold-start latency for large template sets.

Simplified Model (no per-flag tuning):
| Profile | Snapshot | Preload | APCu | Notes |
|---------|----------|---------|------|-------|
| Full (`leanMode = false`) | Enabled | Enabled | Enabled if extension active | Fast path; zero manual tuning |
| Lean (`leanMode = true`) | Disabled | Disabled | Disabled | Lowest overhead |
| Lean + Override (`leanMode = true`, `enableDiscoverySnapshot = true`) | Enabled | Enabled | Enabled if extension active | Targeted acceleration with minimal diagnostics |

Lifecycle (with snapshot active):
1. First scan → miss; build snapshot + fingerprint.
2. Subsequent process → fingerprint verify, restore list (hit) without scanning.
3. Structural change (fingerprint mismatch) → rescan, refresh snapshot.

Symptoms & Guidance:
| Symptom | Likely Cause | Recommendation |
|---------|--------------|----------------|
| Hits never increase | Always lean without override | Enable snapshot via override or disable lean |
| Frequent rescans | Underlying template directories changing legitimately | Accept cost or keep lean if acceptable |
| High initial latency only | No warmup, cold snapshot build | Run warmup or keep full profile |

---
## 4. Dynamic Registry Impact
Dynamic functions/filters are applied once per environment lifecycle.

Tips:
- Batch register early (before first render) to avoid environment resets.
- Use Boolean shorthand only when you truly mean HTML safe output: `true => ['is_safe'=>['html']]`.
- Unregister operations force environment recreation at next render; avoid in hot path.

Cost Model:
| Operation | Relative Cost |
|-----------|---------------|
| Register before first render | O(1) queue cost |
| Register after first render  | Adds function + minor overhead |
| Unregister | Environment rebuilt next render |

---
## 5. Invalidation Efficiency
Invalidate only what changed—don’t `clearCache()` globally unless necessary.

Choice Guide:
| Change Type | Action |
|-------------|-------|
| Single template edited | `invalidateTemplate('emails/welcome')` |
| Few known templates | `invalidateTemplates([...])` |
| Module / Namespace redeployed | `invalidateNamespace('@admin')` |
| Mass refactor / global extension change | `clearCache(true)` + `warmup --all` |

CI Backend Bonus: Namespace/Batch invalidations avoid any directory scan for compiled classes (keys are deleted directly via index list). File backend must iterate directory once per batch.

---
## 6. Diagnostics Interpretation
Call:
```php
$diag = $twig->getDiagnostics();
```
Important Keys:
| Key | Target |
|-----|--------|
| `discovery.hits/misses` | Want growing hits, stable (low) misses |
| `cache.compiled_templates` | Should approach total template count after warmup |
| `warmup.summary` | Track compile errors early |
| `invalidations.cumulative_removed` | Sudden spikes may indicate a buggy invalidation loop |
| `performance.avg_render_time_ms` | Observe trend; large jump => inspect extensions/filters |

---
## 7. Benchmarking Workflow
1. Baseline (cold): clear cache + disable snapshot → load homepage.
2. Enable discovery snapshot + preload; reload → compare delta.
3. Perform full warmup; measure render time again.
4. Activate APCu (if available) and repeat.
5. Record metrics from diagnostics after N renders.

Minimal Script (pseudo):
```php
for ($i=0;$i<10;$i++) { $twig->render('page/home'); }
print_r($twig->getDiagnostics());
```

---
## 8. Failure Handling Philosophy
All persistence writes are best-effort; runtime failures degrade to a safe but slower path (scan or recompile). This keeps production stable even if cache infrastructure is degraded.

---
## 9. Recommended Production Bundle
Full profile (default): no explicit discovery settings needed.
```php
$config->leanMode = false; // auto snapshot + preload + APCu (if available)
```
Lightweight with snapshot:
```php
$config->leanMode = true;
$config->enableDiscoverySnapshot = true; // only acceleration you need
```
Deployment Hook:
```
php spark twig:clear-cache --reinit
php spark twig:warmup --all
```

---
## 10. Quick Health Checklist
| Check | Pass Criteria |
|-------|---------------|
| Discovery reuse | hits >> misses after warm traffic |
| Compile coverage | compiled_templates ~= template count |
| Invalidation rate | Low & intentional |
| Render latency | Stable median & low variance |
| Snapshot freshness | Fingerprint stable between deployments |

---
## 11. Extending Performance Monitoring
- Wrap `Environment::render()` with timing + memory (outside of library).
- Export diagnostics to Prometheus by mapping counters.
- Add a cron job to run `twig:diagnostics --json` and feed logs/metrics backend.

---
## 12. FAQ
| Question | Answer |
|----------|--------|
| Do I need warmup if opcache enabled? | Opcache removes PHP parse cost but first Twig compilation still occurs; warmup avoids that on first live hit. |
| Is APCu required? | No, it is an accelerator only. |
| Why do misses increment twice then stop? | First cold scan + subsequent process fingerprint binding (preload optimization). |
| When should I force warmup? | After bulk template modifications or dynamic extension signature changes. |

---
Happy profiling!

---
## 13. Lean Mode Performance Profile

Lean Mode (`$config->leanMode = true`) offers a low-overhead operational profile by disabling non-essential persistence & diagnostic work:

Disabled by default under Lean:
1. Warmup summary persistence
2. Invalidation history persistence
3. Discovery snapshot (unless re-enabled via override)
4. Dynamic metrics (names & counts) beyond core rendering stats
5. Extended diagnostics name lists

Re-enable selectively with nullable overrides (set to `true`):
```php
$config->leanMode = true;
$config->enableDiscoverySnapshot   = true; // keep snapshot for large template trees
$config->enableWarmupSummary       = true; // capture last warmup result
$config->enableInvalidationHistory = true; // track churn
```

### 13.1 Cost Comparison (Qualitative)
| Component | Full Profile Cost | Lean Default Cost |
|-----------|-------------------|-------------------|
| Warmup summary write | Single JSON write per warmup | Skipped |
| Invalidation state write | JSON write per invalidation event | Skipped |
| Discovery snapshot read (preload) | JSON + fingerprint verify | Skipped unless override enabled |
| Dynamic metrics collection | Array merges + name gathering | Minimal (zeroed counts) |
| Names list memory | O(F + filters + functions) | Eliminated |

### 13.2 When to Use Lean Mode
| Condition | Lean Mode? |
|-----------|------------|
| High traffic & low need for rich diagnostics | Yes |
| Frequent template invalidations requiring audit | No (keep history) |
| Memory constrained FPM workers | Yes |
| Debug session / profiling | No |

See `docs/CACHING.md#21-lean-mode--capability-overrides` for detailed capability matrix.

