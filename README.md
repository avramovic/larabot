# Larabot

Larabot is your personal AI assistant that runs directly on your computer - Windows, Linux, or macOS. Imagine having an intelligent helper that can manage your system, automate recurring or scheduled tasks, check your emails, search the web, and basically do anything you can do with the command line. Larabot connects powerful Large Language Models (LLMs) with your local environment, giving you a secure, owner-only interface (via Telegram) to control and automate your digital life. Whether you want to run scripts, monitor processes, fetch information, or build custom workflows, Larabot is designed to be your all-in-one automation and productivity companion.

For up-to-date documentation and detailed architecture, see the [DeepWiki](https://deepwiki.com/avramovic/larabot).

---

## Key Features

- **Telegram Integration:** Secure, owner-only access to the bot via Telegram chat.
- **LLM-Powered Chat:** Interact with Anthropic, OpenAI, Gemini, or custom LLMs. Supports tool calling and multi-turn conversations.
- **MCP Tool System:** Extensible set of tools for web search, image search, HTTP requests, file transfer, process management, and more.
- **Scheduled Tasks:** Cron-like scheduling of prompts, with results routed to chat, memory, or auto-handled by the LLM.
- **Memory System:** Persistent, retrievable knowledge base with important memories preloaded into context.
- **Process & Job Management:** Queue-based async processing, job supervision, and dashboard for monitoring.
- **Secure Command Execution:** Sandboxed shell/CLI command execution with audit logging.
- **Customizable & Extensible:** Add new tools, models, or integrations easily.

---

## Architecture Overview

- **Laravel App:** Core logic, models, and service providers.
- **MCP Server:** Exposes tools and system operations to the LLM and agents.
- **Telegram Channel:** Handles updates, authorization, and message conversion.
- **LLMChatService:** Orchestrates LLM requests, tool calls, and conversation management.
- **Queue Workers:** Process Telegram updates, scheduled tasks, and tool executions asynchronously.

See [DeepWiki Architecture](https://deepwiki.com/avramovic/larabot/2-architecture) for diagrams and details.

---

## Getting Started

1. **Clone the repository:**
   ```bash
   git clone git@github.com:avramovic/larabot.git
   cd larabot
   ```
2. **Install PHP dependencies:**
   ```bash
   composer install
   ```
3. **Create and configure .env:**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```
4. **Run migrations and seeders:**
   ```bash
   php artisan migrate --seed
   ```
5. **Configure:**
   ```bash
   php artisan larabot:config   # Interactive setup wizard
   ```
5. **Start Telegram listener and queue workers:**
   ```bash
   php artisan larabot:run
   ```
6. **Access the dashboard (optional, in a separte terminal):**
   ```bash
   php artisan larabot:dashboard
   ```

---

## Usage

- **Chat with your bot on Telegram.**
- **Send prompts, run commands, or schedule tasks.**
- **See [DeepWiki](https://deepwiki.com/avramovic/larabot) for advanced usage, troubleshooting, and tool reference.**

---

## Security Note
- ⚠️ The MCP server can execute system commands. Use only in trusted, sandboxed, or development environments.
---

## License

MIT
