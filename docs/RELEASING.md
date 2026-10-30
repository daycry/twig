# Publishing the core and CodeIgniter 4 adapter

Both packages are maintained in `daycry/twig`. The core Composer manifest is at
the repository root; the adapter manifest is in `packages/codeigniter4`.
The adapter distribution repository is `daycry/twig-codeigniter4`, generated from
that directory. Submit code changes to the source repository, then export them;
editing the generated distribution would lose those edits on the next export.

Preparing or updating the distribution repository does not create a stable
release. A release needs matching tags and publication checks for both packages.

## Validate the source

1. Update the changelog, installation examples and migration guide.
2. Open a pull request against `master`; wait for the required checks and merge it.
3. Validate both manifests and run the core, integration and isolated consumer checks.
4. Inspect archives for both packages before creating version tags.

```bash
composer validate --no-check-publish
composer validate --working-dir packages/codeigniter4 --no-check-publish
composer ci
```

The consumer workflow installs both package combinations on PHP 8.2, 8.3, 8.4
and 8.5. The standalone installation must contain neither CodeIgniter nor the
adapter. The integration consumer installs the adapter and discovers its services,
helpers and Spark commands through the production autoloader.

The root `.gitattributes` excludes `packages/`, dependencies, tests and local
state from Git/GitHub archives. Composer's `archive.exclude` handles local
Composer archives. The adapter archive includes its own manifest, README, license
and source.

## Export the adapter

Create the public `daycry/twig-codeigniter4` distribution repository once, without
initializing a README or unrelated history. Export the tested source commit:

```bash
git fetch origin
ADAPTER_COMMIT=$(git subtree split --prefix=packages/codeigniter4 origin/master)
git push https://github.com/daycry/twig-codeigniter4.git "$ADAPTER_COMMIT:refs/heads/main"
```

Use a normal fast-forward push. If the mirror has diverged, inspect the histories
before updating it. Record the source and split commit IDs for each release.
The mirror's `composer.json` is at its root and names `daycry/twig-codeigniter4`.
No local path repositories are needed when installing published packages.

## Register and tag releases

The core keeps the existing `daycry/twig` Packagist entry. Register the adapter
once at https://packagist.org/packages/submit using the public repository URL
`https://github.com/daycry/twig-codeigniter4`. Enable GitHub update integration for
both entries. Registering a package or pushing `main` exposes development versions;
a stable install requires suitable version tags.

After release approval, publish matching version tags for both packages. The
commands below show `v4.0.0`; substitute the next version for later releases.
Publish the core first, then the adapter, whose manifest requires core `^4.0`.
The following commands assume the tested source and mirror have been prepared:

```bash
git tag -a v4.0.0 origin/master -m "Release 4.0.0"
git push origin refs/tags/v4.0.0
git push https://github.com/daycry/twig-codeigniter4.git "$ADAPTER_COMMIT:refs/tags/v4.0.0"
```

Check both Packagist entries have indexed the tags and verify fresh installs:

```bash
composer require daycry/twig:^4.0
# In a separate clean CodeIgniter consumer:
composer require daycry/twig-codeigniter4:^4.0
```

Publish release notes for both packages. Never replace an already published tag;
release a patch version for subsequent corrections. The versioned examples above
apply after publication, not merely after exporting the source tree.
