# PHPX Gap Analysis & Roadmap

## 1. Introduction

This document provides a gap analysis of `phpx` against its primary inspirations and competitors: `npx` (Node.js), `uv` (Python), and the direct competitor **`cpx`** (PHP). The goal is to define a clear, strategic roadmap for `phpx` to become a production-ready, competitive tool for executing PHP packages, with a specific focus on enabling command-line applications and MCP (Model-Context-Protocol) servers.

`phpx` aims to bring the convenience and power of package runners from other ecosystems to PHP developers, learning from the best features available in the market.

## 2. Competitive Landscape: cpx.dev

The discovery of [cpx.dev](https://cpx.dev/) is a significant event. It is a direct competitor with a similar vision and a rich feature set. This raises the stakes and provides a clear benchmark for what users in the PHP community are starting to expect from a package runner.

### Key Features of `cpx` (from Website)
- **Normalized Workflow Commands:** `cpx test`, `cpx check`, `cpx format` automatically detect and run the project's specific tooling (e.g., PHPUnit, Pest, PHPStan, Pint). This is a killer feature that abstracts away project-specific configurations for the developer.
- **On-the-fly Code Execution:** `cpx exec` for files, `cpx exec -r` for raw code, and `cpx tinker` for a REPL, all with project autoloading and even Laravel bootstrapping.
- **Dynamic Dependencies:** A `composer_require()` function to include packages in scratch files.
- **Smart Features:** Automatic class aliasing (no `use` statements needed).

### Key Findings from `cpx` Source Code Analysis
A review of the `cpx` source code confirms its architecture and validates our strategic direction.

-   **Confirms "Task Runner" Model:** The architecture is fundamentally that of a task runner. The main `cpx` executable is a router that dispatches to specific `Command` classes (e.g., `TestCommand`, `FormatCommand`). Each command is designed to execute and terminate. **There is no internal logic for daemonizing or managing persistent background processes**, which is required for MCP servers.
-   **No Convention for Server Execution:** The dispatcher logic in `cpx/cpx` is explicit. It checks for package aliases, direct `vendor/package` strings, or built-in commands. There is **no mechanism for reading a `composer.json` file to find a custom "server" script**. This is the exact opportunity for `phpx`.
-   **Sophisticated Alias System:** `cpx` maintains a large, hardcoded list of packages and their common aliases in `src/PackageAliases.php`, which powers its normalized workflow commands. This is its "secret sauce."

## 3. Core Philosophy Comparison

-   **npx**: A pure package **runner**. Integrates with the `npm` ecosystem.
-   **uv**: A comprehensive **toolchain** (manager, installer, runner). Aims to replace `pip`.
-   **cpx**: A PHP-native **supercharged task runner**. It goes beyond `npx`'s scope by adding workflow normalization and smart execution features tailored specifically for PHP development. It is not a service manager.
-   **phpx**: A package **and service runner**. While it can perform tasks like `cpx`, its key differentiator will be its ability to run and manage long-running services like MCP servers, driven by a simple convention in `composer.json`.

**Conclusion**: The goal for `phpx` is now crystal clear: achieve parity on the essential "task running" features offered by `cpx` while establishing itself as the go-to tool for the more advanced use case of **running services**, a gap that `cpx` does not fill.

## 4. Feature Comparison

| Feature                    | npx (Node.js)                                     | cpx (PHP)                                                | phpx (Current State)                                                                   |
| -------------------------- | ------------------------------------------------- | -------------------------------------------------------- | -------------------------------------------------------------------------------------- |
| **Package Execution**      | ✅ Yes (npm packages)                             | ✅ Yes (Composer packages)                               | ✅ Yes (Composer packages & PHARs)                                                     |
| **Local `bin` Execution**  | ✅ Yes                                            | ✅ Yes                                                   | ❌ No (Relies on Composer's `vendor/bin` but doesn't have a direct pass-through)        |
| **Normalized Workflows**   | ❌ No                                             | ✅ Yes (`test`, `check`, `format`)                       | ❌ No                                                                                  |
| **Arbitrary Code Exec**    | ❌ No                                             | ✅ Yes (`exec`, `tinker`)                                | ❌ No                                                                                  |
| **Dynamic Dependencies**   | ❌ No                                             | ✅ Yes (`composer_require()`)                            | ❌ No                                                                                  |
| **Service Running (Daemon)**| ❌ No                                             | ❌ No                                                    | ❌ No                                                                                  |
| **Convention-Based Server**| ❌ No                                             | ❌ No                                                    | ❌ No (This is the key opportunity)                                                    |
| **Version Pinning**        | ✅ Yes (`pkg@version`)                            | ✅ Yes (`vendor/package:version`)                        | ✅ Yes (`vendor/pkg:version`)                                                          |
| **Caching**                | ✅ Yes                                            | ✅ Yes                                                   | ✅ Yes                                                                                 |
| **Interactive Prompts**    | ✅ Yes                                            | ✅ Yes (Implicit in its update checks)                   | ❌ No                                                                                  |
| **Security**               | Basic prompt for untrusted packages.              | Checks for updates, but otherwise implicit trust.        | ⚠️ None (Implicitly trusts packages/PHARs)                                           |

## 5. Revised Gap Analysis & Roadmap for PHPX

### Phase 1: Establish a Unique Identity & Competitive Baseline

The goal is to immediately establish `phpx`'s key differentiator while matching the most critical features of `cpx`.

1.  **Implement the "Server Runner" Convention (Top Priority):**
    -   **Vision:** This is the core differentiator. `phpx` must become the tool for running services via Cursor deeplinks.
    -   **Action:**
        -   Modify `PackageManager.php` and/or `ExecutionEnvironment.php`.
        -   When resolving a package, inspect its `composer.json` for an `extra.phpx.server` key.
        -   If found, execute the script specified in that key. This makes `phpx eduardocruz/mcp-php-sdk` work seamlessly.
        -   This single feature makes `phpx` uniquely capable of handling the Cursor deeplink use case.

2.  **Normalized Workflow Commands (Competitive Necessity):**
    -   **Gap:** This is the biggest feature gap with `cpx`.
    -   **Action:** Create new command classes in `src/Console/Command/` for `TestCommand`, `CheckCommand`, and `FormatCommand`. Like `cpx`, these commands will check `composer.json` for known tools (PHPUnit, Pest, PHPStan, Pint) and execute the appropriate one. This is essential for developer adoption.

3.  **Interactive Security Prompt:**
    -   **Gap:** No user confirmation before execution.
    -   **Action:** In `PackageManager.php`, before downloading a package, add a confirmation step: `phpx: Need to install [package-name]. Ok to proceed? (y/N)`. Implement a `--yes` flag to bypass it.

### Phase 2: Polish and Solidify

This phase focuses on matching the remaining high-value `cpx` features and improving the user experience.

1.  **Seamless Local & Remote Execution:**
    -   **Gap:** `phpx` needs to flawlessly execute binaries from local dependencies (`./vendor/bin`) and remote packages.
    -   **Action:** In `bin/phpx`, modify the main dispatcher logic. A command like `phpx phpunit` should first check if `./vendor/bin/phpunit` exists and execute it. If not, then proceed with the remote package resolution (`phpunit/phpunit`).

2.  **Basic Code Execution:**
    -   **Gap:** `cpx` offers `exec`. `phpx` should have a basic equivalent.
    -   **Action:** Add an `ExecuteCommand.php` that handles `phpx exec <file.php>`, ensuring it includes the local `vendor/autoload.php` if present.

3.  **Daemon/Background Process Management:**
    -   **Vision:** To be a true service runner, `phpx` needs to manage processes.
    -   **Action:** Introduce a `--daemon` or `-d` flag. When present, `phpx` will need to use process control functions (`pcntl_fork` in PHP) or a library to run the server script as a background process, detached from the terminal. This is a significant step beyond `cpx`.

### Phase 3: Future Vision & Ecosystem Leadership

1.  **Process Management Commands:**
    -   **Vision:** If `phpx` can start daemons, it must be able to manage them.
    -   **Action:** Implement `phpx list-servers`, `phpx stop <server>`, `phpx logs <server>`. This would involve storing PIDs and log file locations, making `phpx` a true process manager for PHP applications.

2.  **Plugin and Alias System:**
    -   **Vision:** Allow users to define their own aliases in a global `~/.config/phpx/config.json` file to simplify commands.

## 6. Conclusion

The competitive landscape has sharpened the vision for `phpx`. The source code for `cpx` confirms that it is an excellent **task runner**, but not a **service runner**. This leaves a clear and valuable lane for `phpx`.

The roadmap is clear:
1.  Immediately build the **server-running convention** to capture the unique deeplink/MCP use case.
2.  Quickly follow up with **normalized workflow commands** to achieve competitive parity for daily tasks.
3.  Solidify the vision by adding true **daemon and process management**, something no other tool in the PHP ecosystem does in this way.

`phpx` has a powerful and unique value proposition. It's time to build it.