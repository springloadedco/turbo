---
name: github-milestone
description: Create well-structured GitHub milestones. Use when planning new phases of work that group related issues together.
---

Milestones group related issues into a phase of work. A well-structured milestone provides context and clear completion criteria for autonomous execution.

## Milestone Description Format

```markdown
## Summary
[1-2 sentences providing context for the agent. What does this milestone accomplish?]

## Key Files
- `path/to/relevant/file.php`
- `path/to/another/file.tsx`

## Done When
- [ ] High-level acceptance criterion 1
- [ ] High-level acceptance criterion 2
```

## Required Sections

| Section | Required | Description |
|---------|----------|-------------|
| Summary | Yes | Brief context for the agent about the milestone's purpose |
| Key Files | No | Paths to important files the agent should examine |
| Done When | Yes | High-level criteria that determine milestone completion |

## Example

```markdown
## Summary
Migrate the Ralph autonomous agent system from file-based (PRD.md + progress.txt) to GitHub-native (milestones, issues, PRs). This enables better collaboration and visibility.

## Key Files
- `app/Services/Ralph/GitHubService.php`
- `app/Console/Commands/Ralph/`
- `config/github.php`

## Done When
- [ ] All ralph:* Artisan commands functional
- [ ] GitHub MCP integration working
- [ ] Legacy files deprecated
```

## Best Practices

1. **Context first** - Explain why this work matters
2. **List key files** - Point to important entry points
3. **Clear completion criteria** - Define what "done" means
4. **Keep milestones focused** - 3-7 issues per milestone is ideal

## GitHub MCP Tool

```
Tool: mcp__github__create_milestone
Parameters:
  owner: "<repo-owner>"
  repo: "<repo-name>"
  title: "Milestone title"
  description: "<markdown description following the format above>"
```
