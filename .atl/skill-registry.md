# Skill Registry — prueba_cibervoluntarios

**Generated**: 2026-05-07  
**Project**: Symfony 7.4 + Doctrine + Twig  
**SDD Mode**: engram

---

## Available Skills

### SDD Workflow Skills
These skills implement the Spec-Driven Development lifecycle:

- **sdd-init** — Initialize SDD in a project (detects stack, conventions, testing)
- **sdd-explore** — Investigate ideas and requirements
- **sdd-propose** — Create change proposals
- **sdd-spec** — Write detailed specifications with scenarios
- **sdd-design** — Create technical design documents
- **sdd-tasks** — Break changes into implementation tasks
- **sdd-apply** — Implement tasks from specs and design
- **sdd-verify** — Validate implementation against specs
- **sdd-archive** — Close and persist completed changes

### User Skills (Generalist)

| Skill | Trigger | Use When |
|-------|---------|----------|
| **branch-pr** | Creating a pull request or preparing changes for review | Preparing PRs after implementation |
| **cognitive-doc-design** | Writing guides, READMEs, RFCs, onboarding, or architecture docs | Documenting specifications or design decisions |
| **comment-writer** | Drafting feedback, review comments, maintainer replies, async messages | Adding context to PRs or issues |
| **issue-creation** | Creating GitHub issues, reporting bugs, requesting features | Planning work upfront with issue-first enforcement |
| **judgment-day** | User says "judgment day", "review adversarial", "dual review", etc. | Triggering parallel adversarial code review |
| **skill-creator** | User asks to create a new skill, add agent instructions, or document patterns | Adding project-specific AI agent skills |
| **work-unit-commits** | Implementing a change, preparing commits, planning chained PRs | Structuring commits as work units instead of file batches |

### Stack-Specific Skills

| Skill | Stack | Trigger | Use When |
|-------|-------|---------|----------|
| **go-testing** | Go | Writing Go tests, using teatest | Writing Go test suites or TUI testing |
| **tailwind-4** | Frontend (CSS) | Styling with Tailwind | Adding or updating Tailwind styles |
| **gentle-ai-chained-pr** | All | PR exceeds 400 changed lines | Splitting large changes into reviewer-friendly slices |

---

## No Project-Level Conventions Found

The project has:
- ✅ EditorConfig (`.editorconfig`)
- ✅ Standard PSR-4 structure
- ❌ No project-specific AGENTS.md or CLAUDE.md
- ❌ No project-specific skills (`.claude/skills/`, `.agent/skills/`, etc.)

**Recommendation**: If the project adopts project-specific coding conventions or AI agent patterns, create a `.atl/agents.md` file to document them.

---

## Project Stack Mapping

**Tech**: PHP 8.2 / Symfony 7.4 / Doctrine ORM / Twig  
**Testing**: PHPUnit 13.1 + Symfony BrowserKit  
**No special triggers** for PHP/Symfony currently.

**Relevant skills for this project**:
- `sdd-*` workflow skills (all projects)
- `cognitive-doc-design` for architecture/API documentation
- `work-unit-commits` when preparing PRs with Symfony code changes
- `comment-writer` for async PR collaboration

---

## SDD Skill Resolution

When `sdd-apply` or other SDD phases run, the orchestrator injects **Compact Rules** from this registry into the sub-agent prompt based on:

1. **File context** — what files the agent will touch (.php, .yaml, .twig, etc.)
2. **Task context** — what the agent is doing (implementing, testing, documenting, etc.)

Since this Symfony project has no project-specific conventions documented, standard SDD rules apply.

