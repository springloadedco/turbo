---
name: github-issue
description: Create atomic GitHub issues for agent execution. Use when breaking down work into single-iteration tasks with precise acceptance criteria.
---

Issues represent individual tasks of a given milestone (plan) completable in a single context window. The quality of your issue directly determines implementation quality.

**Key principle**: If you can't describe exactly what "done" looks like, the issue isn't ready to create.

## Issue Body Format

```markdown
## Description
[What needs to be done. Must be completable in ONE single context window.]

## Acceptance Criteria
- [ ] Specific, verifiable criterion
- [ ] Another criterion
{{ $feedback_loops_checklist }}

## Files
- `path/to/affected/file.php`
```

## Required Sections

| Section | Required | Description |
|---------|----------|-------------|
| Description | Yes | Clear explanation of what needs to be implemented |
| Acceptance Criteria | Yes | Specific, verifiable criteria including feedback loops |
| Files | No | Paths to files that will be created or modified |

## Standard Acceptance Criteria

EVERY issue must include feedback loop criteria relevant to the project's tech stack. Include commands that verify the code works: {{ $feedback_loops }}

Example:
```markdown
{{ $feedback_loops_checklist }}
```

## Example

```markdown
## Description
Create PHP service wrapping Laravel-GitHub for API calls. Single point of access for all GitHub API operations.

## Acceptance Criteria
- [ ] `getMilestones()` returns array of milestone data
- [ ] `getMilestone()` returns single milestone by number
- [ ] `getIssuesForMilestone()` returns issues with labels
- [ ] Handles non-existent resources gracefully
{{ $feedback_loops_checklist }}

## Files
- `app/Services/Ralph/GitHubService.php`
- `tests/Feature/GitHub/GitHubServiceTest.php`
```

## Issue Sizing Guidelines

An issue is **right-sized** when it can be completed in a single context window. Signs of a well-sized issue:

- **Single clear objective** - One thing to accomplish
- **2-4 acceptance criteria** - Not counting standard feedback loops
- **Affects 1-3 files** - Limited scope
- **Describable in one sentence** - If you need paragraphs, split it up

### Common Mistakes

**Too Large** (split into multiple issues):
- "Implement user authentication" -> login issue, registration issue, password reset issue
- "Add admin dashboard" -> stats widget issue, user list issue, activity feed issue

**Too Small** (combine with related work):
- "Add import statement" -> part of the feature that uses it
- "Fix typo in comment" -> part of the file's main changes

**Too Vague** (needs specifics):
- "Improve performance" -> Which endpoint? What metric? What target?
- "Fix bug" -> What's the bug? What's the expected behavior?

## Issue Dependencies

When creating multiple issues:
1. Identify which issues block others
2. Create blocked issues with clear `Depends on #N` note in description
3. Assign appropriate priority labels so high-priority blockers are done first

## Creating Issues

**Preferred: `gh` CLI**

```bash
gh issue create \
  --title "Issue title" \
  --body "$(cat <<'EOF'
## Description
What needs to be done.

## Acceptance Criteria
- [ ] Specific criterion
- [ ] `composer test` passes
EOF
)" \
  --label "priority:high" \
  --label "type:feature" \
  --milestone "Milestone Name"
```

**Fallback: GitHub MCP**

If `gh` CLI is unavailable, the project may have the GitHub MCP server installed:

```
Tool: mcp__github__create_issue
Parameters:
  owner: "<repo-owner>"
  repo: "<repo-name>"
  title: "Issue title"
  body: "<markdown body following the format above>"
  labels: ["priority:high", "type:feature"]
  milestone: <milestone-number>
```
