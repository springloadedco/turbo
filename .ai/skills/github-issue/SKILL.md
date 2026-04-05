---
name: github-issue
description: Create atomic GitHub issues with clear acceptance criteria. Use when breaking down work into single-iteration tasks that can be picked up and completed independently.
---

Issues represent individual tasks that can be completed in a single focused work session. The quality of your issue directly determines implementation quality.

**Key principle**: If you can't describe exactly what "done" looks like, the issue isn't ready to create.

## Issue Body Format

```markdown
## Description
[What needs to be done. Scoped to one focused work session.]

## Acceptance Criteria
- [ ] Specific, verifiable criterion
- [ ] Another criterion

## Files
- `path/to/affected/file.php`
```

## Required Sections

| Section | Required | Description |
|---------|----------|-------------|
| Description | Yes | Clear explanation of what needs to be implemented |
| Acceptance Criteria | Yes | Specific, verifiable criteria for completion |
| Files | No | Paths to files that will be created or modified |

## Example

```markdown
## Description
Add a `sendWelcomeEmail` action that sends a welcome email to newly-registered users. Must be queueable and handle delivery failures gracefully.

## Acceptance Criteria
- [ ] `SendWelcomeEmail` action class exists with `handle(User $user)` method
- [ ] Dispatches via queue (`ShouldQueue`)
- [ ] Logs failures without throwing
- [ ] Unit test covers success and failure cases

## Files
- `app/Actions/SendWelcomeEmail.php`
- `tests/Unit/Actions/SendWelcomeEmailTest.php`
```

## Issue Sizing Guidelines

An issue is **right-sized** when it can be completed in a single focused session. Signs of a well-sized issue:

- **Single clear objective** — one thing to accomplish
- **2-4 acceptance criteria** — not a laundry list
- **Affects 1-3 files** — limited scope
- **Describable in one sentence** — if you need paragraphs, split it up

### Common Mistakes

**Too Large** (split into multiple issues):
- "Implement user authentication" → login issue, registration issue, password reset issue
- "Add admin dashboard" → stats widget issue, user list issue, activity feed issue

**Too Small** (combine with related work):
- "Add import statement" → part of the feature that uses it
- "Fix typo in comment" → part of the file's main changes

**Too Vague** (needs specifics):
- "Improve performance" → Which endpoint? What metric? What target?
- "Fix bug" → What's the bug? What's the expected behavior?

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
