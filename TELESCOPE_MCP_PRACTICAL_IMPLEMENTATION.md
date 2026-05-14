# TelescopeMCP PHP Implementation: Practical Guide

## Executive Summary

After analyzing the actual TelescopeMCP code (1,057 lines of sophisticated Python), here's the practical roadmap for creating a PHP version that actually works with Cursor and other MCP clients.

## What TelescopeMCP Actually Does

**TelescopeMCP is a Laravel debugging powerhouse:**
- Connects directly to Laravel's MySQL database
- Analyzes `telescope_entries` table with 14 specialized tools
- Provides intelligent query analysis (N+1 detection, slow queries)
- Offers advanced search, pagination, and performance insights
- Integrates with Laravel Debugbar for timing analysis

**This is NOT a simple 200-line script** - it's a comprehensive Laravel debugging solution.

## The Practical Implementation Plan

### Phase 1: Minimal Viable MCP Server (Week 1)

**Goal**: Get something working in Cursor immediately

**File Structure:**
```
telescope-mcp-php/
├── composer.json
├── telescope-mcp-server.php  # Main executable
├── src/
│   ├── TelescopeMCP.php     # Core server class
│   └── DatabaseConnection.php
└── README.md
```

**1. Create `composer.json`:**
```json
{
    "name": "eduardocruz/telescope-mcp-php",
    "description": "Laravel Telescope MCP Server in PHP",
    "require": {
        "php": ">=8.1",
        "eduardocruz/mcp-php-sdk": "dev-main"
    },
    "autoload": {
        "psr-4": {
            "TelescopeMCP\\": "src/"
        }
    },
    "bin": ["telescope-mcp-server.php"]
}
```

**2. Create `telescope-mcp-server.php` (Executable Entry Point):**
```php
#!/usr/bin/env php
<?php

require_once __DIR__ . '/vendor/autoload.php';

use TelescopeMCP\TelescopeMCP;
use ModelContextProtocol\Transport\StdioTransport;

// Get database URL from environment
$dbUrl = $_ENV['DB_URL'] ?? 'mysql://sail:password@127.0.0.1:3306/laravel';

// Create and start the MCP server
$server = new TelescopeMCP($dbUrl);
$transport = new StdioTransport();
$server->connect($transport);
```

**3. Create `src/TelescopeMCP.php` (Core Implementation):**
```php
<?php

namespace TelescopeMCP;

use ModelContextProtocol\Server\McpServer;
use PDO;
use PDOException;

class TelescopeMCP extends McpServer
{
    private PDO $db;
    
    public function __construct(string $dbUrl)
    {
        parent::__construct('Laravel Telescope Explorer', '1.0.0');
        
        // Parse database URL and connect
        $this->db = $this->connectToDatabase($dbUrl);
        
        // Register the essential tools
        $this->registerTools();
    }
    
    private function connectToDatabase(string $dbUrl): PDO
    {
        // Parse mysql://user:pass@host:port/db format
        $parsed = parse_url($dbUrl);
        
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            $parsed['host'],
            $parsed['port'] ?? 3306,
            ltrim($parsed['path'], '/')
        );
        
        try {
            $pdo = new PDO($dsn, $parsed['user'], $parsed['pass'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            
            // Test connection
            $pdo->query('SELECT 1 FROM telescope_entries LIMIT 1');
            
            return $pdo;
        } catch (PDOException $e) {
            throw new \Exception("Database connection failed: " . $e->getMessage());
        }
    }
    
    private function registerTools(): void
    {
        // Tool 1: Recent requests
        $this->registerTool('requests', [
            'properties' => [
                'page' => ['type' => 'integer', 'default' => 1],
                'search' => ['type' => 'string']
            ]
        ], [$this, 'getRequests']);
        
        // Tool 2: Get specific request details
        $this->registerTool('get_request', [
            'properties' => [
                'batch_id' => ['type' => 'string']
            ],
            'required' => ['batch_id']
        ], [$this, 'getRequest']);
        
        // Tool 3: Get request queries
        $this->registerTool('get_request_queries', [
            'properties' => [
                'batch_id' => ['type' => 'string'],
                'page' => ['type' => 'integer', 'default' => 1]
            ],
            'required' => ['batch_id']
        ], [$this, 'getRequestQueries']);
        
        // Tool 4: Search requests
        $this->registerTool('search_requests', [
            'properties' => [
                'controller' => ['type' => 'string'],
                'status' => ['type' => 'integer'],
                'min_duration_ms' => ['type' => 'number'],
                'method' => ['type' => 'string'],
                'uri_pattern' => ['type' => 'string'],
                'page' => ['type' => 'integer', 'default' => 1]
            ]
        ], [$this, 'searchRequests']);
        
        // Tool 5: Slow queries
        $this->registerTool('slow_queries', [
            'properties' => [
                'time_threshold_ms' => ['type' => 'number', 'default' => 100],
                'limit' => ['type' => 'integer', 'default' => 20]
            ]
        ], [$this, 'getSlowQueries']);
    }
    
    public function getRequests(array $params): array
    {
        $page = $params['page'] ?? 1;
        $search = $params['search'] ?? null;
        $limit = 10;
        $offset = ($page - 1) * $limit;
        
        $sql = "
            SELECT batch_id, type, content, created_at
            FROM telescope_entries 
            WHERE type = 'request'
        ";
        
        $sqlParams = [];
        
        if ($search) {
            $sql .= " AND JSON_EXTRACT(content, '$.uri') LIKE :search";
            $sqlParams['search'] = "%{$search}%";
        }
        
        $sql .= " ORDER BY created_at DESC LIMIT :limit OFFSET :offset";
        
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        
        foreach ($sqlParams as $key => $value) {
            $stmt->bindValue(":{$key}", $value);
        }
        
        $stmt->execute();
        $requests = $stmt->fetchAll();
        
        // Format the response
        $formatted = [];
        foreach ($requests as $request) {
            $content = json_decode($request['content'], true);
            $formatted[] = [
                'batch_id' => $request['batch_id'],
                'method' => $content['method'] ?? 'GET',
                'uri' => $content['uri'] ?? '',
                'status' => $content['response_status'] ?? 200,
                'duration_ms' => $content['duration'] ?? 0,
                'created_at' => $request['created_at']
            ];
        }
        
        return [
            'content' => [
                [
                    'type' => 'text',
                    'text' => json_encode([
                        'requests' => $formatted,
                        'page' => $page,
                        'has_more' => count($requests) === $limit
                    ], JSON_PRETTY_PRINT)
                ]
            ]
        ];
    }
    
    public function getRequest(array $params): array
    {
        $batchId = $params['batch_id'];
        
        $sql = "
            SELECT type, content 
            FROM telescope_entries 
            WHERE batch_id = :batch_id
            ORDER BY sequence
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['batch_id' => $batchId]);
        $entries = $stmt->fetchAll();
        
        if (empty($entries)) {
            throw new \Exception("Request not found: {$batchId}");
        }
        
        $result = [
            'batch_id' => $batchId,
            'entries' => []
        ];
        
        foreach ($entries as $entry) {
            $result['entries'][$entry['type']] = json_decode($entry['content'], true);
        }
        
        return [
            'content' => [
                [
                    'type' => 'text',
                    'text' => json_encode($result, JSON_PRETTY_PRINT)
                ]
            ]
        ];
    }
    
    public function getRequestQueries(array $params): array
    {
        $batchId = $params['batch_id'];
        $page = $params['page'] ?? 1;
        $limit = 10;
        $offset = ($page - 1) * $limit;
        
        $sql = "
            SELECT content 
            FROM telescope_entries 
            WHERE batch_id = :batch_id AND type = 'query'
            ORDER BY sequence
            LIMIT :limit OFFSET :offset
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':batch_id', $batchId);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        $queries = [];
        while ($row = $stmt->fetch()) {
            $content = json_decode($row['content'], true);
            $queries[] = [
                'sql' => $content['sql'] ?? '',
                'time_ms' => $content['time'] ?? 0,
                'connection' => $content['connection_name'] ?? 'mysql'
            ];
        }
        
        return [
            'content' => [
                [
                    'type' => 'text',
                    'text' => json_encode([
                        'queries' => $queries,
                        'page' => $page,
                        'batch_id' => $batchId
                    ], JSON_PRETTY_PRINT)
                ]
            ]
        ];
    }
    
    public function searchRequests(array $params): array
    {
        $conditions = [];
        $sqlParams = [];
        
        $sql = "
            SELECT batch_id, content, created_at
            FROM telescope_entries 
            WHERE type = 'request'
        ";
        
        if (!empty($params['controller'])) {
            $conditions[] = "JSON_EXTRACT(content, '$.controller_action') LIKE :controller";
            $sqlParams['controller'] = "%{$params['controller']}%";
        }
        
        if (!empty($params['status'])) {
            $conditions[] = "JSON_EXTRACT(content, '$.response_status') = :status";
            $sqlParams['status'] = $params['status'];
        }
        
        if (!empty($params['min_duration_ms'])) {
            $conditions[] = "JSON_EXTRACT(content, '$.duration') >= :min_duration";
            $sqlParams['min_duration'] = $params['min_duration_ms'];
        }
        
        if (!empty($params['method'])) {
            $conditions[] = "JSON_EXTRACT(content, '$.method') = :method";
            $sqlParams['method'] = $params['method'];
        }
        
        if (!empty($params['uri_pattern'])) {
            $conditions[] = "JSON_EXTRACT(content, '$.uri') LIKE :uri_pattern";
            $sqlParams['uri_pattern'] = "%{$params['uri_pattern']}%";
        }
        
        if (!empty($conditions)) {
            $sql .= " AND " . implode(" AND ", $conditions);
        }
        
        $sql .= " ORDER BY created_at DESC LIMIT 20";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($sqlParams);
        $requests = $stmt->fetchAll();
        
        $formatted = [];
        foreach ($requests as $request) {
            $content = json_decode($request['content'], true);
            $formatted[] = [
                'batch_id' => $request['batch_id'],
                'method' => $content['method'] ?? 'GET',
                'uri' => $content['uri'] ?? '',
                'status' => $content['response_status'] ?? 200,
                'duration_ms' => $content['duration'] ?? 0,
                'controller' => $content['controller_action'] ?? '',
                'created_at' => $request['created_at']
            ];
        }
        
        return [
            'content' => [
                [
                    'type' => 'text',
                    'text' => json_encode([
                        'requests' => $formatted,
                        'total_found' => count($formatted)
                    ], JSON_PRETTY_PRINT)
                ]
            ]
        ];
    }
    
    public function getSlowQueries(array $params): array
    {
        $threshold = $params['time_threshold_ms'] ?? 100;
        $limit = $params['limit'] ?? 20;
        
        $sql = "
            SELECT batch_id, content, created_at
            FROM telescope_entries 
            WHERE type = 'query' 
            AND JSON_EXTRACT(content, '$.time') > :threshold
            ORDER BY JSON_EXTRACT(content, '$.time') DESC
            LIMIT :limit
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':threshold', $threshold);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        $queries = [];
        while ($row = $stmt->fetch()) {
            $content = json_decode($row['content'], true);
            $queries[] = [
                'batch_id' => $row['batch_id'],
                'sql' => $content['sql'] ?? '',
                'time_ms' => $content['time'] ?? 0,
                'connection' => $content['connection_name'] ?? 'mysql',
                'created_at' => $row['created_at']
            ];
        }
        
        return [
            'content' => [
                [
                    'type' => 'text',
                    'text' => json_encode([
                        'slow_queries' => $queries,
                        'threshold_ms' => $threshold,
                        'total_found' => count($queries)
                    ], JSON_PRETTY_PRINT)
                ]
            ]
        ];
    }
}
```

### How to Actually Run This in Cursor

**1. Install the Package:**
```bash
cd telescope-mcp-php
composer install
chmod +x telescope-mcp-server.php
```

**2. Configure Cursor (`.cursor/mcp.json` in your Laravel project):**
```json
{
  "mcpServers": {
    "telescope-php": {
      "type": "stdio",
      "command": "php",
      "args": [
        "/absolute/path/to/telescope-mcp-php/telescope-mcp-server.php"
      ],
      "env": {
        "DB_URL": "mysql://sail:password@127.0.0.1:3306/laravel"
      }
    }
  }
}
```

**3. Test It:**
```bash
# Test the server directly
DB_URL="mysql://sail:password@127.0.0.1:3306/laravel" php telescope-mcp-server.php

# In Cursor, you can now ask:
# "Show me recent telescope requests"
# "Find slow queries in my Laravel app"
# "What requests are failing?"
```

## Why This Approach Works

1. **Uses your existing mcp-php-sdk** - Leverages the work you've already done
2. **Single executable file** - Easy to configure in Cursor
3. **Direct database access** - No web server needed
4. **Follows MCP protocol** - Works with any MCP client
5. **Extensible** - Easy to add more tools

## The Real Value Proposition

**This isn't just about porting TelescopeMCP** - it's about creating the **first Laravel-focused MCP ecosystem in PHP**:

1. **TelescopeMCP PHP** (debugging)
2. **Laravel Artisan MCP** (code generation)
3. **Laravel Log MCP** (log analysis)
4. **Laravel Queue MCP** (job monitoring)
5. **Laravel Cache MCP** (cache management)

## Next Steps

1. **Build the MVP** (above code, 1-2 days)
2. **Test with Cursor** (verify it actually works)
3. **Add remaining tools** (match Python feature parity)
4. **Package for distribution** (Composer package)
5. **Build the Laravel MCP ecosystem**

## The Strategic Opportunity

**You're not just building a tool - you're creating the foundation for PHP's AI-powered development future.** Every Laravel developer who uses this will see the power of MCP and want more PHP-based MCP servers.

This could be the **"Laravel Telescope moment"** for MCP in PHP - the killer app that makes everyone realize they need this technology. 