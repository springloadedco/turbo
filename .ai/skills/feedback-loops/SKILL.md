---
name: feedback-loops
description: Run the project's verification commands before claiming work complete. Use ALWAYS when finishing a task, claiming work done, committing, or creating a PR. Blocks completion until all checks pass.
---

This project has specific verification commands that MUST pass before work is considered complete. These are the project's **feedback loops** — automated checks that catch regressions, style violations, type errors, and broken tests.

## The Rule

**Do not claim work is done, commit code, or create a PR unless you have run the project's feedback loops and every command passed.**

If any command fails, the task is not complete. Fix the failures first, re-run the full checklist, and only then claim completion.

## When This Skill Fires

- You are about to say "done", "complete", "finished", "ready for review", or "ready to merge"
- You are about to run `git commit` or create a commit via any tool
- You are about to run `gh pr create` or open a PR
- You are reporting a task status as DONE in any workflow or plan

In all of these moments: stop, run the checklist below, report pass/fail for each, fix any failures, then continue.

## The Checklist

{{ $feedback_loops_checklist }}

## How to Run

1. Run each command in order, from the project root
2. Capture the exit code for each
3. If a command fails, read the output, fix the issue, then re-run from the start
4. Do NOT skip commands or claim "probably passes" — run them
5. Do NOT edit test assertions to make failing tests pass — fix the underlying code

## Reporting Results

When you report on your work (in a PR body, completion message, or task update), include the feedback loop status. Example:

```
Feedback loops:
✓ composer test
✓ composer analyse
✓ composer format --test
```

If a command is intentionally skipped (e.g., no changes to frontend → skipping npm run types), note that explicitly.

## Common Mistakes

- **Claiming done without running the loops.** Even if "the change is small" — run them.
- **Fixing only the failing test instead of the underlying code.** A failing test usually indicates a real problem.
- **Ignoring warnings.** Warnings from static analysis tools (PHPStan, Pint) are loops output and count as failures.
- **Running only some of the commands.** Run all of them. Each one catches different problems.
