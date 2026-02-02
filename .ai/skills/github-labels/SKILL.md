---
name: github-labels
description: Apply consistent labels to GitHub issues. Use when creating or categorizing issues by type and priority.
---

You use a standard set of labels to categorize and prioritize issues.

## Type Labels

| Label | Color | Description |
|-------|-------|-------------|
| `type:bug` | Red (#d73a4a) | Something isn't working |
| `type:feature` | Blue (#0075ca) | New feature or enhancement |
| `type:refactor` | Gray (#666666) | Code improvement, no behavior change |
| `type:docs` | Purple (#7057ff) | Documentation only |

## Priority Labels

| Label | Color | Description |
|-------|-------|-------------|
| `priority:high` | Red (#b60205) | Should be done first |
| `priority:medium` | Yellow (#fbca04) | Normal priority |
| `priority:low` | Green (#0e8a16) | Nice to have |

## Priority Ordering

When selecting the next issue to work on:

1. Filter to open issues only
2. Sort by priority label: `high` > `medium` > `low` > unlabeled
3. Within same priority, sort by issue number (oldest first)
4. Return first match

## Usage

Labels should be included when creating issues:

```
Tool: mcp__github__create_issue
Parameters:
  owner: "<repo-owner>"
  repo: "<repo-name>"
  title: "Issue title"
  body: "<issue body>"
  labels: ["priority:high", "type:feature"]
  milestone: <milestone-number>
```

## Label Combinations

Common combinations:

| Scenario | Labels |
|----------|--------|
| Critical bug | `type:bug`, `priority:high` |
| New feature | `type:feature`, `priority:medium` |
| Tech debt | `type:refactor`, `priority:low` |
| README update | `type:docs`, `priority:low` |
| Blocking issue | `type:bug`, `priority:high` |

## Best Practices

1. **Always assign priority** - Helps with issue ordering
2. **Always assign type** - Clarifies what kind of work it is
3. **Use high priority sparingly** - Reserve for true blockers
4. **Default to medium** - Most issues are normal priority
