
# PHPX AI Factory Agent  
_“Midnight‑Coder” Extension Documentation_  

> **Purpose**  
> Extend **[PHPX](https://github.com/eduardocruz/phpx)** into an autonomous, local‑LLM‑powered task runner that codes micro‑projects overnight and leaves you a ready‑to‑review output every morning.

---

## 1. Prerequisites  

| Requirement | Version / Notes |
|-------------|-----------------|
| **macOS**   | Apple Silicon (M‑series) |
| **Ollama**  | `v0.1.34` or newer (`brew install ollama`) |
| **Local LLM** | `deepseek-coder-v2:latest` (≈ 9 GB, pulled via `ollama pull deepseek-coder-v2`) |
| **PHPX**    | Current local checkout (`main` branch) |
| **PHP**     | ≥ 8.2 + PDO SQLite extension (for lightweight DB) |

```bash
# one‑time setup
ollama serve &
ollama pull deepseek-coder-v2
phpx self:update
```

---

## 2. Folder Layout  

```text
phpx-project/
├── phpx.php
├── MidnightAgent/            # new extension code lives here
│   ├── AgentService.php
│   └── commands.php          # registers CLI commands
├── tasks.sqlite              # task queue (SQLite)
├── outputs/                  # generated code or docs per task
└── logs/                     # JSON logs for each run
```

*Keep everything self‑contained; no external services required.*

---

## 3. Task Schema  

| Field | Type | Example | Notes |
|-------|------|---------|-------|
| `id`  | int  | `42` | PK (autoincrement) |
| `title` | string | `"Create landing page"` | |
| `description` | text | `"TailwindCSS, responsive"` | prompt body |
| `status` | enum | `pending • running • done • error` | |
| `created_at` | datetime | `2025‑04‑25 T23:30:00‑03:00` | |
| `updated_at` | datetime | – | |

> **Tip:** use SQLite (`tasks.sqlite`) — dead simple and transactional.

---

## 4. PHPX Commands  

| Command | Purpose | Signature |
|---------|---------|-----------|
| `phpx agent:init` | bootstrap folders + SQLite file | *(no args)* |
| `phpx agent:add-task` | insert new task | `--title="..." --desc="..."` |
| `phpx agent:run` | overnight loop | `--max=NN` optional limit |
| `phpx agent:logs` | tail latest runs | `--last=20` |

### Command Skeleton (`commands.php`)

```php
<?php
// MidnightAgent/commands.php
use MidnightAgent\AgentService;

Artisan::command('agent:init', fn() => AgentService::init());

Artisan::command('agent:add-task {--title=} {--desc=}', function () {
    AgentService::addTask(
        $this->option('title'),
        $this->option('desc')
    );
});

Artisan::command('agent:run {--max=}', fn() =>
    AgentService::run($this->option('max'))
);

Artisan::command('agent:logs {--last=10}', fn() =>
    AgentService::tail($this->option('last'))
);
```

---

## 5. Prompt Template  

```
You are a senior full‑stack developer.

Task:
{{title}}

Details:
{{description}}

Return ONLY the code (wrap each file in triple‑backtick blocks labelled with the filename).
Ensure the project is runnable out‑of‑the‑box.
```

*Save as `MidnightAgent/templates/task_prompt.txt`.*

---

## 6. Agent Loop (pseudo‑code)

```php
while ($task = $queue->nextPending()) {
    $prompt = Template::render('task_prompt.txt', $task);
    $response = Ollama::generate('deepseek-coder-v2', $prompt);

    Files::storeOutput($task->id, $response);
    $queue->markDone($task->id);

    sleep(2); // be nice to CPU
}
```

> **Ollama Client:** simple HTTP POST to `http://localhost:11434/api/generate`.

---

## 7. Daily Workflow  

1. **Evening** – add tasks  
   ```bash
   phpx agent:add-task --title="Notes API" --desc="Laravel 12, CRUD, SQLite"
   ```
2. **Night** – run loop (manual or cron)  
   ```bash
   phpx agent:run
   ```
3. **Morning** – review output  
   ```bash
   phpx agent:logs --last=20
   code outputs/42/
   ```

---

## 8. Cursor Todo List 🗒️  

Paste this into Cursor’s TODO list to scaffold faster:

- [ ] Create `MidnightAgent/AgentService.php` with `init`, `addTask`, `run`, `tail`
- [ ] Build `tasks.sqlite` migration in `agent:init`
- [ ] Implement simple Ollama client (`GuzzleHttp` acceptable)
- [ ] Parse LLM output → files (````filename
…````)
- [ ] Unit tests for AgentService (PHPUnit)
- [ ] Add cron entry: `0 23 * * * cd /path && phpx agent:run`
- [ ] Document first success in README 🎉  

---

## 9. Roadmap  

| Milestone | Description |
|-----------|-------------|
| **v0.1** | Basic queue + single‑agent execution |
| **v0.2** | Error handling & retries |
| **v0.3** | Parallel workers (if RAM allows) |
| **v0.4** | Planner (deepseek‑r1) + Coder separation |
| **v1.0** | Auto‑PR to GitHub + CI |

---

### License  

MIT — same as PHPX.  
Contributions & issue reports welcome!

---

*Created 2025-04-25 20:39*
