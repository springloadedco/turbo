---
name: github-pr-comment
description: Add progress comments to PRs during agent execution. Use when documenting work completed, decisions made, and any blockers encountered.
---

Adds progress comments to PRs to track work done and provide visibility into execution status.

## PR Progress Comment Format

```markdown
## Progress

### Completed
- What was done (bullet points)

### Decisions
- Key choices and reasoning

### Files Changed
- `path/to/file.php`

### Blockers
[If any, otherwise omit section]
```

## Sections

| Section | Required | Description |
|---------|----------|-------------|
| Completed | Yes | Bullet list of work completed |
| Decisions | No | Key architectural or implementation decisions |
| Files Changed | Yes | Paths to modified files |
| Blockers | No | Only include if there are blockers |

## Example

```markdown
## Progress

### Completed
- Created GitHubService with milestone and issue methods
- Added error handling for non-existent resources
- Wrote 8 feature tests

### Decisions
- Used arrays instead of DTOs for simple data passing
- Wrapped exceptions in custom GitHubException class

### Files Changed
- `app/Services/Ralph/GitHubService.php`
- `app/Exceptions/GitHubException.php`
- `tests/Feature/GitHub/GitHubServiceTest.php`
```

## Example with Blockers

```markdown
## Progress

### Completed
- Implemented API endpoint
- Added validation rules

### Files Changed
- `app/Http/Controllers/Api/ExampleController.php`
- `app/Http/Requests/ExampleRequest.php`

### Blockers
- Tests failing due to missing database migration
- Need clarification on authentication requirements
```

## GitHub MCP Tool

```
Tool: mcp__github__add_issue_comment
Parameters:
  owner: "<repo-owner>"
  repo: "<repo-name>"
  issue_number: <pr-number>
  body: "<markdown body following the format above>"
```

## Linking Issues and PRs

- PR body should include `Fixes #N` to auto-close issues
- Reference related issues in description when helpful
- Use milestone assignment to group related work
