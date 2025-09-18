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
The discovery phase enumerates logical template names. It can be the largest cold-start latency source for applications with hundreds of templates.

Key Config Flags (`Config\\Twig`):
```php
$discoveryPersistList = true;      // Write snapshot JSON / CI entry
$discoveryPreload     = true;      // Restore snapshot on process start
$discoveryUseAPCu     = true;      // Share list across PHP-FPM workers
$discoveryFingerprintMtimeDepth = 0; // Raise to 1–2 if deep structural changes
```

Lifecycle:
1. First scan → miss, build snapshot & fingerprint.
2. Next process (preload on) → fingerprint verify, promote to hit without scan.
3. On directory structure change (mtime difference) → fingerprint mismatch → new scan + snapshot.

APCu provides near O(1) restore: hash lookup + array fetch.

Symptoms & Fixes:
| Symptom | Likely Cause | Fix |
|---------|--------------|-----|
| Hits never increase | Snapshot disabled or fingerprint always changing | Enable persist/preload; reduce depth to 0 |
| Frequent rescans post edit | Depth too high for frequent file operations | Lower depth |
| Templates missing from list | Paths misconfigured | Check `Config\\Twig::$paths` |

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
```php
$config->cacheBackend                   = 'ci';
$config->discoveryPersistList           = true;
$config->discoveryPreload               = true;
$config->discoveryUseAPCu               = true;   // if server supports
$config->discoveryFingerprintMtimeDepth = 0;      // bump to 1 only if missed changes
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
