# Daycry Twig documentation

Daycry Twig provides a standalone rendering toolkit and an optional CodeIgniter 4
adapter. Both Composer packages are developed in this repository. The adapter
is exported from `packages/codeigniter4` to its distribution repository.

Version **4.0** changes framework namespaces. Use the installation guide for
new applications and the migration guide when upgrading from 3.x. The
installation guide also covers local development from the source checkout.

| Task | Guide |
|---|---|
| Choose a package, install it and render the first template | [Installation](INSTALLATION.md) |
| Upgrade an existing 3.x application | [Migration to 4.0](MIGRATION.md) |
| Configure services, logging and persistence boundaries | [Services and extension points](SERVICES.md) |
| Warm, invalidate or clear compiled templates | [Cache behavior](CACHING.md) |
| Configure discovery and measure rendering | [Performance](PERFORMANCE.md) |
| Read diagnostics or configure the debug toolbar | [Diagnostics reference](DIAGNOSTICS_REFERENCE.md) |
| Diagnose installation, rendering or cache problems | [Troubleshooting](TROUBLESHOOTING.md) |
| Export the adapter and publish matching package versions | [Release procedure](RELEASING.md) |

For a quick API overview, see the [project README](../README.md). Contributors
should use [CONTRIBUTING.md](../CONTRIBUTING.md); changes to either package belong
in this source repository. The adapter distribution is generated from it.
