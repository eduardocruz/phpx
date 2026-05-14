# Laravel Prism + MCP Relay: The Native Laravel Approach

## Executive Summary

Instead of building a standalone MCP server, we can create a **Laravel MCP Relay** that integrates with [Laravel Prism](https://prismphp.com/) to provide MCP capabilities directly within Laravel applications. This approach is more native, simpler to deploy, and leverages existing Laravel infrastructure.

## Architecture Comparison

### **Standalone MCP Server Approach:**
```
Cursor (MCP Client)
  ↓ (MCP Protocol)
Standalone PHP MCP Server
  ↓ (Database connection)
Laravel Database
```

### **Laravel Prism + Relay Approach:**
```
Cursor (MCP Client)
  ↓ (MCP Protocol)
Laravel App with Prism + MCP Relay
  ↓ (Direct access)
Telescope Data / Application Data
```

## Why This Is Better

### ✅ **Advantages of Prism + Relay**

1. **Native Laravel Integration**
   - Works with existing Laravel applications
   - Direct access to application data and models
   - No external database connections needed

2. **Leverages Laravel Prism**
   - Built-in LLM provider integrations (OpenAI, Anthropic, Ollama)
   - Fluent text generation API
   - Structured output handling
   - First-class testing support

3. **Simpler Deployment**
   - Just install a Laravel package
   - No separate server to manage
   - Uses existing Laravel infrastructure

4. **Better Security**
   - No database credentials in MCP configuration
   - Uses Laravel's existing authentication/authorization
   - Application-level access controls

### ❌ **Disadvantages**

1. **Requires Laravel Application**
   - Only works with Laravel apps (not standalone PHP)
   - Application must be running

2. **Different Distribution Model**
   - Can't use phpx for distribution
   - Requires Composer installation in each Laravel app

## Technical Implementation

### **Package Structure**
```
laravel-mcp-relay/
├── src/
│   ├── McpRelayServiceProvider.php
│   ├── Http/
│   │   └── Controllers/McpController.php
│   ├── Console/
│   │   └── Commands/McpServerCommand.php
│   ├── Tools/
│   │   ├── TelescopeAnalyzer.php
│   │   ├── LogAnalyzer.php
│   │   ├── DatabaseInspector.php
│   │   └── ModelAnalyzer.php
│   ├── Resources/
│   │   ├── RouteInspector.php
│   │   └── ConfigurationReader.php
│   └── Relay/
│       ├── McpRelay.php
│       └── PrismBridge.php
├── config/
│   └── mcp-relay.php
└── routes/
    └── mcp.php
```

### **Installation & Setup**
```bash
# Install in any Laravel application
composer require eduardocruz/laravel-mcp-relay

# Publish configuration
php artisan vendor:publish --tag=mcp-relay

# Start MCP server
php artisan mcp:serve
```

### **Service Provider Registration**
```php
<?php

namespace EduardoCruz\LaravelMcpRelay;

use Illuminate\Support\ServiceProvider;

class McpRelayServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->mergeConfigFrom(__DIR__.'/../config/mcp-relay.php', 'mcp-relay');
        
        $this->app->singleton(McpRelay::class, function ($app) {
            return new McpRelay(
                $app->make(PrismBridge::class),
                config('mcp-relay')
            );
        });
    }

    public function boot()
    {
        $this->loadRoutesFrom(__DIR__.'/../routes/mcp.php');
        
        if ($this->app->runningInConsole()) {
            $this->commands([
                Commands\McpServerCommand::class,
            ]);
            
            $this->publishes([
                __DIR__.'/../config/mcp-relay.php' => config_path('mcp-relay.php'),
            ], 'mcp-relay');
        }
    }
}
```

### **MCP Server Command**
```php
<?php

namespace EduardoCruz\LaravelMcpRelay\Console\Commands;

use Illuminate\Console\Command;
use EduardoCruz\LaravelMcpRelay\McpRelay;

class McpServerCommand extends Command
{
    protected $signature = 'mcp:serve {--port=8001}';
    protected $description = 'Start the MCP relay server';

    public function handle(McpRelay $relay)
    {
        $port = $this->option('port');
        
        $this->info("Starting MCP relay server on port {$port}...");
        
        // Start HTTP server for MCP protocol
        $relay->serve($port);
    }
}
```

### **Telescope Tool Integration**
```php
<?php

namespace EduardoCruz\LaravelMcpRelay\Tools;

use Laravel\Telescope\Telescope;
use Laravel\Telescope\Storage\EntryQueryOptions;
use PrismPhp\Prism\Tool;

class TelescopeAnalyzer extends Tool
{
    public function definition(): array
    {
        return [
            'name' => 'analyze_telescope_entries',
            'description' => 'Analyze Laravel Telescope entries for performance issues',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'type' => [
                        'type' => 'string',
                        'enum' => ['request', 'query', 'job', 'exception'],
                        'description' => 'Type of entries to analyze'
                    ],
                    'hours' => [
                        'type' => 'integer',
                        'default' => 24,
                        'description' => 'Hours to look back'
                    ]
                ],
                'required' => ['type']
            ]
        ];
    }

    public function handle(array $parameters): string
    {
        $type = $parameters['type'];
        $hours = $parameters['hours'] ?? 24;
        
        $entries = Telescope::query(new EntryQueryOptions([
            'type' => $type,
            'limit' => 100,
            'hours' => $hours
        ]));
        
        // Use Laravel Prism to analyze the entries
        return $this->analyzeWithPrism($entries, $type);
    }
    
    private function analyzeWithPrism($entries, $type): string
    {
        $prompt = "Analyze these Laravel Telescope {$type} entries and identify performance issues:\n\n";
        $prompt .= json_encode($entries->toArray(), JSON_PRETTY_PRINT);
        
        return \Prism::text()
            ->using('claude-3-sonnet')
            ->withPrompt($prompt)
            ->generate();
    }
}
```

### **MCP Relay Bridge**
```php
<?php

namespace EduardoCruz\LaravelMcpRelay\Relay;

use PrismPhp\Prism\Prism;

class McpRelay
{
    public function __construct(
        private PrismBridge $prismBridge,
        private array $config
    ) {}
    
    public function handleMcpRequest(array $request): array
    {
        $method = $request['method'];
        $params = $request['params'] ?? [];
        
        return match($method) {
            'initialize' => $this->handleInitialize($params),
            'tools/list' => $this->handleToolsList(),
            'tools/call' => $this->handleToolCall($params),
            'resources/list' => $this->handleResourcesList(),
            'resources/read' => $this->handleResourceRead($params),
            default => $this->handleUnknownMethod($method)
        };
    }
    
    private function handleToolCall(array $params): array
    {
        $toolName = $params['name'];
        $arguments = $params['arguments'] ?? [];
        
        // Route to appropriate Laravel Prism tool
        $result = $this->prismBridge->callTool($toolName, $arguments);
        
        return [
            'content' => [
                [
                    'type' => 'text',
                    'text' => $result
                ]
            ]
        ];
    }
}
```

## Cursor Integration

### **HTTP Transport Deeplink**
```json
{
  "command": "curl",
  "args": [
    "-X", "POST",
    "-H", "Content-Type: application/json",
    "http://localhost:8001/mcp",
    "-d", "@-"
  ]
}
```

### **Artisan Command Deeplink**
```json
{
  "command": "php",
  "args": ["artisan", "mcp:serve", "--port=8001"]
}
```

## User Experience

### **For Laravel Developers:**
1. **Install**: `composer require eduardocruz/laravel-mcp-relay`
2. **Configure**: Optionally publish and customize config
3. **Start**: `php artisan mcp:serve`
4. **Connect**: Use Cursor deeplink to connect to `http://localhost:8001/mcp`

### **Available Tools (Examples):**
- `analyze_telescope_entries` - Performance analysis
- `inspect_slow_queries` - Database optimization
- `analyze_exceptions` - Error pattern detection
- `inspect_routes` - Route analysis
- `analyze_models` - Model relationship insights
- `check_configuration` - Config validation

## Comparison with Standalone Approach

| Aspect | Standalone MCP Server | Laravel Prism + Relay |
|--------|----------------------|----------------------|
| **Installation** | `phpx telescope-mcp-server` | `composer require laravel-mcp-relay` |
| **Database Access** | External connection string | Direct Laravel access |
| **Authentication** | Manual configuration | Laravel's built-in auth |
| **Data Access** | Limited to Telescope tables | Full application access |
| **LLM Integration** | Custom implementation | Laravel Prism features |
| **Deployment** | Separate process | Part of Laravel app |
| **Security** | Database credentials exposed | Application-level security |

## Recommendation

**Use Laravel Prism + Relay when:**
- ✅ Working with Laravel applications
- ✅ Want native Laravel integration
- ✅ Need access to full application context
- ✅ Prefer Laravel-style package management

**Use Standalone MCP Server when:**
- ✅ Need framework-agnostic solution
- ✅ Want to distribute via phpx
- ✅ Working with non-Laravel PHP applications
- ✅ Need lightweight, focused tool

## Implementation Priority

Given that Laravel Prism already handles the LLM integration complexity, the **Laravel Prism + Relay approach** might be:

1. **Faster to implement** (leverage existing Prism features)
2. **More Laravel-native** (fits Laravel ecosystem better)
3. **Easier to maintain** (fewer moving parts)
4. **More powerful** (access to full Laravel application)

This could be the **primary approach** for Laravel developers, with the standalone MCP server as a **secondary option** for broader PHP ecosystem coverage. 