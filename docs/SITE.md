# Building and publishing the documentation

The public documentation is hosted at <https://daycry.github.io/twig/> and built
from `docs/` using MkDocs Material. `mkdocs.yml` defines the navigation, theme,
search and strict link validation. Source changes belong in this repository.

## Build locally

Create a workspace-local environment, install the locked build dependencies and
build the same site configuration used by CI:

```bash
python -m venv build/docs-venv
# Linux/macOS:
build/docs-venv/bin/python -m pip install -r docs/requirements.txt
build/docs-venv/bin/python -m mkdocs build --strict
# Windows:
build/docs-venv/Scripts/python.exe -m pip install -r docs/requirements.txt
build/docs-venv/Scripts/python.exe -m mkdocs build --strict
```

The output is `build/docs-site`. For a local preview, run `python -m mkdocs serve`
with the environment's Python executable. Python 3.13 is used in CI.

`docs/requirements.in` lists the direct dependencies. To update the lock, create
a clean environment, install that file and regenerate `docs/requirements.txt`
with `python -m pip freeze`. Validate the strict build before committing both.
Update the full action commit pins in `.github/workflows/docs.yml` against the
official action releases when refreshing the workflow.

## Deployment

The Docs workflow builds pull requests without deployment. Changes merged to
`master`, or a manual run on `master`, build and upload one Pages artifact and
then deploy that artifact to the `github-pages` environment. Pages must use
GitHub Actions as its build source. The build job has read-only repository
access; only the deployment job receives Pages write and OIDC permissions.

Deployment is serialized. A failed build cannot replace the live site. After a
successful deployment, check the public home, installation and migration pages,
CSS and search index. Confirm the deployed commit in the workflow/environment.

For recovery, revert the source change and run Docs on `master`, or rerun a
previous successful Docs workflow when its artifact is still available. Keep the
site checks in strict mode; fix invalid navigation and links in the source.

The GitHub documentation index (`docs/README.md`), roadmap records and build
requirements are excluded from the public site. Site tooling is also excluded
from the distributed PHP runtime package.
