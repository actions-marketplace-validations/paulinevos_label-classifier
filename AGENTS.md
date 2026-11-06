# AGENTS.md

Notes for coding agents working in this repository.

## Setup

```bash
composer install
cp .env.example .env   # then fill in JEV_API_KEY; the real environment wins over .env
```

The bins load `.env` from the repository root with `usePutenv(true)` because
the code reads `getenv()` throughout.

## Running the command locally

```bash
GITHUB_TOKEN=... bin/label-pull-request --repository=acme/widgets --pr=123
```

The GitHub environment variables (`GITHUB_TOKEN`, `GITHUB_REPOSITORY`,
`GITHUB_EVENT_PATH`, `GITHUB_API_URL`) are picked up from the environment, so
inside a repository checkout only `--pr` or `--repository` is typically
needed.

| Option | Default |
| --- | --- |
| `--repository` | `GITHUB_REPOSITORY` |
| `--pr` | the pull request in `GITHUB_EVENT_PATH` |
| `--event-path` | `GITHUB_EVENT_PATH` |
| `--api-url` | `GITHUB_API_URL` or `https://api.github.com` |
| `--labels-config` | `config/labels.json` |
| `--thresholds` | `LABEL_THRESHOLDS`, e.g. `Bug=0.9,Feature=0.7` |
| `--abstain-margin` | `LABEL_ABSTAIN_MARGIN` or `0.05` |
| `--model` | `JEV_MODEL` or `jev-1.13` |
| `--jev-endpoint` | `JEV_ENDPOINT` or `https://api.typesafe.ai/v1/systemone` |
| `--docs-paths` | `LABEL_DOCS_PATHS` or `README.md,UPGRADE.md,docs/en/index.rst` |
| `--input` | none; evaluates frozen pull request data from JSON instead of the GitHub API |

The command only suggests. With `GITHUB_OUTPUT` set (GitHub Actions) the
suggestions are written there as `labels=` and `advisory=`.

## Benchmarking (local tooling, git-ignored)

`benchmarks/` holds the benchmark runner, its tests, and (outside this
repository) the frozen manifests. Ground truth is fetched with the GitHub API
once and never re-pulled; do not commit manifests, dumps, or annotations.

```bash
JEV_API_KEY=... benchmarks/bin/benchmark /path/to/manifest.json
DUMP=dumps/odm.json JEV_API_KEY=... benchmarks/bin/benchmark /path/to/manifest.json
vendor/bin/phpunit --configuration benchmarks/phpunit.xml.dist
```

A dump holds per-case probabilities, decision kinds, and the frozen input; it
feeds the annotation workbench in `tools/annotator.html` (drag the dump files
onto the page).

## Tests and static analysis

```bash
composer test                    # package tests
vendor/bin/phpstan analyse src --level=8 --no-progress
```

Conventions: PHP 8.1, strict types, final classes, readonly properties
(readonly *classes* are 8.2+ and must not be used), no getters where a public
readonly property works, one indentation level per method, no `else`.

## Repository quirks

- The `bin/label-pull-request` autoloader checks the package's own vendor
  first, then the consumer's (`vendor/…/bin` proxy runs the package bin).
- Question ids sent to the System One API are `q0`, `q1`, … — string keys.
  Sequential numeric keys would make PHP collapse the questions map into a
  JSON array, which the API rejects with a misleading 422.
- The evaluation API answers live in `answers[id].noul`; usage in
  `usage.input_tokens`/`output_tokens`, resolved model in `model`.
