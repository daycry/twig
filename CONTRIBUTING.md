# Contributing

Thanks for considering a contribution! This file captures the local
expectations of `daycry/twig`. The general flow is the standard fork → branch
→ PR loop.

## Requirements

- PHP **>= 8.2** (the codebase uses enums, readonly properties, and intersection types).
- Composer 2.x.

```bash
composer install
```

## Quality gates

```bash
composer ci          # cs (dry-run) + deduplicate + analyze + test
composer cs-fix      # apply cs-fixer changes
composer analyze     # phpstan
composer test        # phpunit
```

`composer ci` is the canonical green-light check before pushing. CI runs the
same on PHP 8.2, 8.3, 8.4 and 8.5.

## What to send

- **Bug fixes** — include a regression test that fails on `master` and passes
  with the patch.
- **New CLI commands / public methods** — extend `AbstractTwigCommand`,
  return integer exit codes, support `--json` when output is structured.
- **Behavior changes touching cache / persistence** — note the migration in
  `CHANGELOG.md` `[Unreleased]` (Added / Changed / Fixed / Security).
- **Breaking changes** — flag explicitly in the PR description and target the
  next major in `composer.json`.

## Style

- `php-cs-fixer` config is in `.php-cs-fixer.dist.php` (CodeIgniter4 preset).
  Run `composer cs-fix` before committing.
- Don't add docblocks that just restate the type signature; comment only when
  the *why* is non-obvious.
- Prefer interfaces from `Daycry\Twig\Contracts\` for new dependencies — keeps
  things mockable in tests.
- New persisted JSON shapes go through `Daycry\Twig\Support\PersistenceDecoder`
  with a schema describing required keys/types.

## Commit & PR conventions

- Subject under ~70 characters; describe the change, not the file.
- Wrap body at 100 cols; explain *why* the change matters.
- One logical change per PR; rebase rather than merge.
- Mention the issue or `CHANGELOG.md` section in the body when relevant.

## Reporting security issues

Do **not** open a public GitHub issue for security findings. Email the
maintainer (see `composer.json` authors) and allow time for a coordinated fix.
