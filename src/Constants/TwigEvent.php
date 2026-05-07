<?php

namespace Daycry\Twig\Constants;

/**
 * Public event identifiers dispatched via CodeIgniter's `event()` system and used
 * in structured log lines (`event=<value>`).
 *
 * Usage:
 *   event(TwigEvent::WarmupAfter->value, $payload);
 *   log_message('debug', sprintf('event=%s ...', TwigEvent::CacheCleared->value));
 *
 * Backed by string so existing call sites that pass literal names continue to work.
 */
enum TwigEvent: string
{
    // Warmup
    case WarmupAfter = 'twig:warmup:after';

    // Cache lifecycle
    case CacheCleared  = 'twig.cache.cleared';
    case CacheEnabled  = 'twig.cache.enabled';
    case CacheDisabled = 'twig.cache.disabled';

    // Invalidation
    case TemplateInvalidated  = 'twig.template.invalidated';
    case TemplatesInvalidated = 'twig.templates.invalidated';
    case NamespaceInvalidated = 'twig.namespace.invalidated';

    // Dynamic registry
    case FunctionRegistered   = 'twig.function.registered';
    case FunctionUnregistered = 'twig.function.unregistered';
    case FilterRegistered     = 'twig.filter.registered';
    case FilterUnregistered   = 'twig.filter.unregistered';
    case ExtensionRegistered  = 'twig.extension.registered';

    // Lifecycle
    case Reset          = 'twig.reset';
    case LoaderReplaced = 'twig.loader.replaced';
    case PathAdded      = 'twig.path.added';
}
