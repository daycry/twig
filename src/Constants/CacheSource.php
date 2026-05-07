<?php

namespace Daycry\Twig\Constants;

/**
 * Origin of the in-memory discovery list. Surfaced via diagnostics so operators
 * can tell whether a request hit a fresh scan, the persisted snapshot, or APCu.
 */
enum CacheSource: string
{
    case Scan             = 'scan';
    case Persisted        = 'persisted';
    case PersistedPreload = 'persisted-preload';
    case Apcu             = 'apcu';
}
