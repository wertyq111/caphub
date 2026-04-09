# Caphub Rule Memory

## Workspace Structure

- Primary project path: `/Volumes/T7/Program/Agent/caphub/caphub-dev`
- This workspace currently behaves like a single Laravel application workspace rather than a multi-repo blog-style overview workspace.
- Unless the user explicitly asks otherwise, treat `caphub-dev` as the only code root for implementation, debugging, and verification.

## Runtime Facts

- Runtime stack is based on Laravel Sail and `compose.yaml`.
- Backend debugging and verification should be performed on the remote Docker environment instead of the local workspace.
- The remote backend should be operated through Laravel Sail by default.
- Default local ports from the current `.env`:
  - App: `8090`
  - Vite: `5179`
  - MySQL: `3310`
  - Redis: `6399`
- If runtime testing is required, prefer using the Sail setup already defined in the project.
- Remote host: `ssh ubuntu@10.10.9.184`
- Remote project directory: `/data/agent/projects/caphub`

## Runtime Safety Rules

- Before any frontend or backend refactor operation, ask the user for confirmation first.
- Before running `docker compose up` in any form, including `docker compose up -d`, `docker compose up --build`, rebuild, or recreate variants, ask the user for confirmation first.
- Local work is for code authoring only. Backend tests should not be treated as locally authoritative.
- If backend runtime verification, debugging, or test execution is needed, first connect to `ssh ubuntu@10.10.9.184`, switch to `/data/agent/projects/caphub`, sync the updated code into that directory, then run commands from the remote Docker environment.
- Unless the user explicitly asks otherwise, use `sail` commands on the remote backend for application, queue, migration, and test workflows.

## Filesystem Hygiene

- Never sync, copy, or include `._*` files in runtime sync or deployment-related operations.
- Before remote sync or runtime verification work, delete local `._*` files in the relevant project directory first.
- The current workspace already contains `._*` files under `caphub-dev`, so treat cleanup as a required precondition before any sync-style operation.

## Commit and Change Routing

- Keep implementation changes inside `/Volumes/T7/Program/Agent/caphub/caphub-dev`.
- Do not scatter application changes into the wrapper workspace unless the user explicitly asks for workspace-level documentation or tooling updates.
- Do not mix unrelated cleanup with task-specific code changes unless the user asks for that cleanup.

## Collaboration

- If a task can be split into multiple independent workstreams, prefer parallel sub-agents.

## Decision Principles

- Use first-principles thinking. Start from the original requirement and the underlying problem instead of assuming the user already knows the exact target solution or path.
- Stay cautious about intent. If the motivation, target, or success criteria are unclear, stop and discuss before committing to implementation or proposing a plan.
- When providing a modification or refactor plan, do not propose compatibility-style or patch-style solutions.
- Do not over-engineer. Use the shortest correct path that satisfies the stated requirement and does not violate these principles.
- Do not introduce unstated extras such as fallback, downgrade, or hedge logic unless the user explicitly asks for them.
- Every proposed plan must be logically correct and validated end-to-end before it is presented or executed.
