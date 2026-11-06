# Label classifier

Classifies pull requests by label policy. For every configured label, the
package asks a System One (Jev) evaluation API one independent yes/no question
about one shared pull request context — title, description, commit messages,
changed file paths, diff, and documentation from the base revision — and
combines the returned probabilities with per-label thresholds in PHP.

The command only ever **suggests** labels; the caller decides what to do with
the suggestion. Inside a GitHub Actions run, the suggestions are written to
`$GITHUB_OUTPUT` as `labels=` (suggested for applying) and `advisory=`
(threshold-clearing labels that are reported but not suggested for applying).

## Using it from a GitHub workflow

Define this workflow in the consuming repository — it installs the classifier
from this repository (pinned to a version tag; upgrade deliberately), lets it
suggest labels for a newly opened pull request, and applies the suggestion by
adding labels, so existing labels are preserved and nothing is removed:

```yaml
name: "Label pull request"

on:
  pull_request:
    types:
      - "opened"
  workflow_dispatch:
    inputs:
      number:
        description: "Pull request number to label when triggering manually"
        required: true
        type: "string"

permissions:
  contents: "read"
  issues: "write"
  pull-requests: "read"

concurrency:
  group: "label-pull-request-${{ github.event.pull_request.number || inputs.number }}"
  cancel-in-progress: true

jobs:
  label:
    name: "Label pull request"
    runs-on: "ubuntu-latest"

    outputs:
      labels: "${{ steps.suggest.outputs.labels }}"
      advisory: "${{ steps.suggest.outputs.advisory }}"

    steps:
      - name: "Install the label classifier"
        run: |
          mkdir -p "${RUNNER_TEMP}/labeler"
          cd "${RUNNER_TEMP}/labeler"
          printf '{"repositories":[{"type":"vcs","url":"https://github.com/paulinevos/label-classifier"}],"require":{"vos/label-classifier":"^1.0.0"}}\n' > composer.json
          composer install --no-interaction --no-progress

      # The command only suggests labels; this workflow decides what happens
      # with the suggestion.
      - id: "suggest"
        name: "Suggest labels"
        env:
          PR_OPTION: "${{ inputs.number && format('--pr={0}', inputs.number) || '' }}"
          JEV_API_KEY: ${{ secrets.JEV_API_KEY }}
        run: php "${RUNNER_TEMP}/labeler/vendor/bin/label-pull-request" "$PR_OPTION"

      # Adding labels only: labels already on the pull request are preserved,
      # and nothing is removed.
      - name: "Apply suggested labels"
        if: "steps.suggest.outputs.labels != ''"
        env:
          GH_TOKEN: ${{ github.token }}
          NUMBER: ${{ github.event.pull_request.number || inputs.number }}
          LABELS: ${{ steps.suggest.outputs.labels }}
        run: |
          echo "$LABELS" | tr ',' '\n' | while read -r label; do
            [ -n "$label" ] && gh pr edit "$NUMBER" --repo "${{ github.repository }}" --add-label "$label"
          done

      # Tell people what happened to their pull request, once.
      - name: "Comment about the automatic labels"
        if: "steps.suggest.outputs.labels != ''"
        env:
          GH_TOKEN: ${{ github.token }}
          NUMBER: ${{ github.event.pull_request.number || inputs.number }}
          LABELS: ${{ steps.suggest.outputs.labels }}
        run: |
          if gh pr view "$NUMBER" --repo "${{ github.repository }}" --json comments --jq '.comments[].body' | grep -q 'label-classifier'; then
            exit 0
          fi
          gh pr comment "$NUMBER" --repo "${{ github.repository }}" --body "<!-- label-classifier -->Applied the labels \`${LABELS}\` automatically. They were suggested by an AI classifier from the pull request content and have not been reviewed by a human — please check that they are correct and remove any that do not fit."
```

The manual `workflow_dispatch` trigger exists because `pull_request: opened`
runs the workflow from the base branch: the automatic trigger only goes live
once the workflow is merged, while a manual run with a pull request number
works immediately. The same workflow ships as a workflow template in this
repository's `.github/workflow-templates/`.

What the consuming repository needs:

| Requirement | Purpose |
| --- | --- |
| `JEV_API_KEY` secret | authenticates against the evaluation API |
| `issues: write` permission | applying labels |
| `JEV_MODEL` variable, optional | the model; defaults to `jev-1.13` |
| `JEV_ENDPOINT` variable, optional | the API endpoint; defaults to `https://api.typesafe.ai/v1/systemone`, set it for a gateway such as OpenCode's |

## Configuration

`config/labels.json` defines one policy per label: the question asked, the
threshold on the yes-probability, and the application mode.

| Application mode | Behavior when the answer clears the threshold |
| --- | --- |
| `auto` | the label is suggested |
| `advisory` | the label is reported separately, never suggested for applying |
| `manual` | the label is never suggested; for labels decided by release management |

## Overrides

Every policy comes from one JSON file, so repositories with their own label
vocabulary pass their own file instead of editing this package:

```bash
bin/label-pull-request --labels-config=/path/to/labels.json
```

The file uses the same shape as `config/labels.json` — one entry per label,
each with a `question`, a `threshold` between 0 and 1, and an `application`
mode. Anything the file defines replaces the default policies entirely.

To keep the shipped policies and adjust only individual thresholds, pass a
comma-separated list; each entry must name a configured label:

```bash
bin/label-pull-request --thresholds="Bug=0.9,dependencies=0.95"
```

The band below a threshold in which a case counts as abstained instead of
rejected works the same way:

```bash
bin/label-pull-request --abstain-margin=0.1
```

Each of these has an environment variable equivalent for workflow use:
`LABEL_THRESHOLDS`, `LABEL_ABSTAIN_MARGIN`, and `LABEL_DOCS_PATHS` (the
documentation paths loaded from the base revision).

## Tests

```bash
composer test
```
