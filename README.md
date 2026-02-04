# Larabot

Larabot is an extended Laravel project that includes an MCP (Multi-Command Processor) server. The MCP server enables execution of CLI commands, task automation, integration with AI agents, and advanced control over local or remote environments through a standard Laravel API.

## What is the MCP server?

The MCP server is an extension of a Laravel application that enables:
- Execution of shell/CLI commands via HTTP API or agent
- File, git repository, package, and process management
- Automation of DevOps, CI/CD, and administrative tasks
- Integration with AI agents for advanced automation
- Secure and controlled command execution (sandboxing, audit, logging)

## How does the MCP server work?

The MCP server uses Laravel's command and tool system to process requests. Each CLI command is executed in a sandboxed environment, and the results (stdout, stderr, exit code) are returned via the API. The server can be extended with additional tools for specific tasks.

Example capabilities:
- Running shell commands (`ls`, `git`, `composer`, `php artisan`, ...)
- Installing packages (`brew`, `apt`, `npm`, `pip`, ...)
- Managing repositories (`git clone`, `git push`, ...)
- Accessing system information (OS, CPU, disk, RAM)
- Automating tests, builds, and deployment processes

## Local setup and usage

1. **Clone the repository:**
   ```bash
   git clone git@github.com:avramovic/larabot.git
   cd larabot
   ```
2. **Install PHP dependencies:**
   ```bash
   composer install
   ```
3. **Install Node.js dependencies (optional):**
   ```bash
   npm install
   ```
4. **Create the .env file:**
   ```bash
   cp .env.example .env
   # Edit configuration as needed
   ```
5. **Run migrations and seeders (optional):**
   ```bash
   php artisan migrate --seed
   ```
6. **Start the local server:**
   ```bash
   php artisan serve
   ```
   The server will be available at http://localhost:8000/mcp/larabot

7. **Using the MCP server:**
   - The MCP server is available through API routes and can be used to execute commands, test, and develop AI agents.
   - Example: execute a CLI command via API or through an agent.

## Note
- ⚠️ The MCP server has access to the local CLI, so use with caution. ⚠️
- It is recommended to use it in a development environment or on sandboxed machines.
- Plan is to add more useful tools and features over time.

---

## License

MIT
