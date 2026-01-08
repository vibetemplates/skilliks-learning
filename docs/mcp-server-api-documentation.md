# MCP Server API Documentation

This documentation provides a comprehensive guide for creating an MCP (Model Context Protocol) server that interfaces with the Learning Community API endpoints.

## Table of Contents

1. [Overview](#overview)
2. [Architecture](#architecture)
3. [API Endpoints Reference](#api-endpoints-reference)
4. [Authentication](#authentication)
5. [Implementation Guide](#implementation-guide)
6. [Example Code](#example-code)
7. [Testing](#testing)
8. [Deployment](#deployment)

## Overview

The MCP server acts as a bridge between AI models and the Learning Community API, providing structured access to communities, projects, programs, courses, and lessons data. This server implements the Model Context Protocol specification to enable AI assistants to interact with your learning platform data.

### Key Features

- RESTful API integration with Learning Community endpoints
- Multiple authentication methods support
- Structured data retrieval for AI consumption
- Error handling and response formatting
- CORS-enabled for cross-origin requests

## Architecture

```
┌─────────────────┐     ┌─────────────────┐     ┌──────────────────┐
│   AI Assistant  │────▶│   MCP Server    │────▶│ Learning API v1  │
│    (Claude)     │◀────│                 │◀────│                  │
└─────────────────┘     └─────────────────┘     └──────────────────┘
       MCP                    HTTP/REST              MySQL DB
    Protocol                  Requests
```

### Components

1. **MCP Server**: Handles MCP protocol communication and translates requests
2. **API Client**: Makes HTTP requests to the Learning Community API
3. **Response Formatter**: Converts API responses to MCP-compatible format
4. **Authentication Handler**: Manages API key and session authentication

## API Endpoints Reference

### Base URL
```
https://your-domain.com/api/v1/
```

### Communities Endpoints

#### List All Communities
```http
GET /api/v1/communities
```

**Response Example:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "name": "Tech Learning Hub",
      "description": "A community for technology enthusiasts",
      "created_at": "2024-01-15T10:30:00Z",
      "member_count": 150
    }
  ]
}
```

#### Get Community Details
```http
GET /api/v1/communities/{id}
```

**Parameters:**
- `id` (integer): Community ID

**Response Example:**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "name": "Tech Learning Hub",
    "description": "A community for technology enthusiasts",
    "created_at": "2024-01-15T10:30:00Z",
    "member_count": 150,
    "admin_users": ["admin@example.com"],
    "settings": {
      "is_public": true,
      "requires_approval": false
    }
  }
}
```

#### Create Community (Requires Authentication)
```http
POST /api/v1/communities
Content-Type: application/json
X-API-Key: your-api-key

{
  "name": "New Community",
  "description": "Description of the new community"
}
```

#### Update Community (Requires Admin)
```http
PUT /api/v1/communities/{id}
Content-Type: application/json
X-API-Key: your-api-key

{
  "name": "Updated Community Name",
  "description": "Updated description"
}
```

### Projects Endpoints

#### List Community Projects
```http
GET /api/v1/projects?community_id={community_id}
```

**Query Parameters:**
- `community_id` (integer): Filter by community

**Response Example:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "name": "Web Development Project",
      "description": "Building a responsive website",
      "community_id": 1,
      "status": "active",
      "created_at": "2024-02-01T14:00:00Z"
    }
  ]
}
```

#### Get Project Details
```http
GET /api/v1/projects/{id}
```

#### Get Project Skills
```http
GET /api/v1/projects/{id}/skills
```

**Response Example:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "name": "JavaScript",
      "level": "intermediate"
    },
    {
      "id": 2,
      "name": "React",
      "level": "beginner"
    }
  ]
}
```

#### Get Project Members
```http
GET /api/v1/projects/{id}/members
```

### Programs Endpoints

#### List Community Programs
```http
GET /api/v1/programs?community_id={community_id}
```

**Response Example:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "name": "Full Stack Development Program",
      "description": "Comprehensive web development training",
      "community_id": 1,
      "duration_weeks": 12,
      "start_date": "2024-03-01"
    }
  ]
}
```

### Courses Endpoints

#### List Community Courses
```http
GET /api/v1/courses?community_id={community_id}
```

#### Get Course Lessons
```http
GET /api/v1/courses/{id}/lessons
```

**Response Example:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "title": "Introduction to JavaScript",
      "description": "Learn JavaScript basics",
      "course_id": 1,
      "order": 1,
      "duration_minutes": 45,
      "content_type": "video"
    }
  ]
}
```

## Authentication

The API supports three authentication methods:

### 1. API Key Header
```http
X-API-Key: your-api-key-here
```

### 2. Bearer Token
```http
Authorization: Bearer your-api-key-here
```

### 3. Query Parameter
```http
GET /api/v1/communities?api_key=your-api-key-here
```

### Obtaining API Keys

API keys can be generated through the web interface at:
```
https://your-domain.com/settings/api-keys
```

## Implementation Guide

### MCP Server Structure

```javascript
// mcp-server.js
class LearningCommunityMCPServer {
  constructor(config) {
    this.apiBaseUrl = config.apiBaseUrl;
    this.apiKey = config.apiKey;
  }

  // MCP Protocol Methods
  async initialize() {
    // Initialize MCP server
  }

  async listTools() {
    return [
      {
        name: "list_communities",
        description: "List all available learning communities",
        inputSchema: {
          type: "object",
          properties: {}
        }
      },
      {
        name: "get_community",
        description: "Get details of a specific community",
        inputSchema: {
          type: "object",
          properties: {
            id: { type: "integer", description: "Community ID" }
          },
          required: ["id"]
        }
      },
      {
        name: "list_projects",
        description: "List projects in a community",
        inputSchema: {
          type: "object",
          properties: {
            community_id: { type: "integer", description: "Community ID" }
          },
          required: ["community_id"]
        }
      },
      // Add more tools...
    ];
  }

  async executeTool(toolName, inputs) {
    switch(toolName) {
      case "list_communities":
        return await this.listCommunities();
      case "get_community":
        return await this.getCommunity(inputs.id);
      case "list_projects":
        return await this.listProjects(inputs.community_id);
      // Handle other tools...
    }
  }

  // API Methods
  async makeRequest(endpoint, method = 'GET', data = null) {
    const url = `${this.apiBaseUrl}${endpoint}`;
    const options = {
      method,
      headers: {
        'X-API-Key': this.apiKey,
        'Content-Type': 'application/json'
      }
    };

    if (data && method !== 'GET') {
      options.body = JSON.stringify(data);
    }

    const response = await fetch(url, options);
    return await response.json();
  }

  async listCommunities() {
    const result = await this.makeRequest('/communities');
    return {
      content: [{
        type: "text",
        text: JSON.stringify(result.data, null, 2)
      }]
    };
  }

  async getCommunity(id) {
    const result = await this.makeRequest(`/communities/${id}`);
    return {
      content: [{
        type: "text",
        text: JSON.stringify(result.data, null, 2)
      }]
    };
  }

  async listProjects(communityId) {
    const result = await this.makeRequest(`/projects?community_id=${communityId}`);
    return {
      content: [{
        type: "text",
        text: JSON.stringify(result.data, null, 2)
      }]
    };
  }
}
```

### Configuration File

```json
{
  "name": "learning-community-api",
  "version": "1.0.0",
  "description": "MCP server for Learning Community API",
  "mcp": {
    "version": "0.1.0"
  },
  "config": {
    "apiBaseUrl": "https://your-domain.com/api/v1",
    "apiKey": "your-api-key-here"
  }
}
```

## Example Code

### Python Implementation

```python
import requests
from typing import Dict, List, Any
from mcp.server import Server, Tool

class LearningCommunityMCPServer(Server):
    def __init__(self, api_base_url: str, api_key: str):
        super().__init__()
        self.api_base_url = api_base_url
        self.api_key = api_key
        
    def _make_request(self, endpoint: str, method: str = "GET", data: Dict = None) -> Dict:
        """Make HTTP request to the API"""
        url = f"{self.api_base_url}{endpoint}"
        headers = {
            "X-API-Key": self.api_key,
            "Content-Type": "application/json"
        }
        
        response = requests.request(method, url, headers=headers, json=data)
        response.raise_for_status()
        return response.json()
    
    @Tool(
        name="list_communities",
        description="List all available learning communities"
    )
    async def list_communities(self) -> Dict[str, Any]:
        result = self._make_request("/communities")
        return {"content": [{"type": "text", "text": str(result["data"])}]}
    
    @Tool(
        name="get_community",
        description="Get details of a specific community"
    )
    async def get_community(self, id: int) -> Dict[str, Any]:
        result = self._make_request(f"/communities/{id}")
        return {"content": [{"type": "text", "text": str(result["data"])}]}
    
    @Tool(
        name="list_projects",
        description="List projects in a community"
    )
    async def list_projects(self, community_id: int) -> Dict[str, Any]:
        result = self._make_request(f"/projects?community_id={community_id}")
        return {"content": [{"type": "text", "text": str(result["data"])}]}

# Initialize and run the server
if __name__ == "__main__":
    server = LearningCommunityMCPServer(
        api_base_url="https://your-domain.com/api/v1",
        api_key="your-api-key-here"
    )
    server.run()
```

### TypeScript Implementation

```typescript
import { MCPServer, Tool, ToolResult } from '@modelcontextprotocol/sdk';

interface Config {
  apiBaseUrl: string;
  apiKey: string;
}

class LearningCommunityMCPServer extends MCPServer {
  private apiBaseUrl: string;
  private apiKey: string;

  constructor(config: Config) {
    super();
    this.apiBaseUrl = config.apiBaseUrl;
    this.apiKey = config.apiKey;
  }

  private async makeRequest(endpoint: string, method: string = 'GET', data?: any): Promise<any> {
    const url = `${this.apiBaseUrl}${endpoint}`;
    const options: RequestInit = {
      method,
      headers: {
        'X-API-Key': this.apiKey,
        'Content-Type': 'application/json'
      }
    };

    if (data && method !== 'GET') {
      options.body = JSON.stringify(data);
    }

    const response = await fetch(url, options);
    return await response.json();
  }

  @Tool({
    name: 'list_communities',
    description: 'List all available learning communities'
  })
  async listCommunities(): Promise<ToolResult> {
    const result = await this.makeRequest('/communities');
    return {
      content: [{
        type: 'text',
        text: JSON.stringify(result.data, null, 2)
      }]
    };
  }

  @Tool({
    name: 'get_community',
    description: 'Get details of a specific community',
    inputSchema: {
      type: 'object',
      properties: {
        id: { type: 'integer', description: 'Community ID' }
      },
      required: ['id']
    }
  })
  async getCommunity({ id }: { id: number }): Promise<ToolResult> {
    const result = await this.makeRequest(`/communities/${id}`);
    return {
      content: [{
        type: 'text',
        text: JSON.stringify(result.data, null, 2)
      }]
    };
  }

  @Tool({
    name: 'list_projects',
    description: 'List projects in a community',
    inputSchema: {
      type: 'object',
      properties: {
        community_id: { type: 'integer', description: 'Community ID' }
      },
      required: ['community_id']
    }
  })
  async listProjects({ community_id }: { community_id: number }): Promise<ToolResult> {
    const result = await this.makeRequest(`/projects?community_id=${community_id}`);
    return {
      content: [{
        type: 'text',
        text: JSON.stringify(result.data, null, 2)
      }]
    };
  }
}

// Initialize and start the server
const server = new LearningCommunityMCPServer({
  apiBaseUrl: 'https://your-domain.com/api/v1',
  apiKey: process.env.API_KEY || 'your-api-key-here'
});

server.start();
```

## Testing

### Unit Tests

```python
import pytest
from unittest.mock import Mock, patch
from learning_community_mcp_server import LearningCommunityMCPServer

class TestLearningCommunityMCPServer:
    @pytest.fixture
    def server(self):
        return LearningCommunityMCPServer(
            api_base_url="https://test.com/api/v1",
            api_key="test-key"
        )
    
    @patch('requests.request')
    def test_list_communities(self, mock_request, server):
        mock_request.return_value.json.return_value = {
            "success": True,
            "data": [{"id": 1, "name": "Test Community"}]
        }
        
        result = server.list_communities()
        assert result["content"][0]["text"] == '[{"id": 1, "name": "Test Community"}]'
```

### Integration Tests

```bash
# Test the MCP server with the actual API
export API_KEY="your-test-api-key"
export API_BASE_URL="https://staging.your-domain.com/api/v1"

# Run the MCP server
python mcp_server.py

# In another terminal, test with MCP client
mcp-client test learning-community-api list_communities
mcp-client test learning-community-api get_community --id 1
```

## Deployment

### Docker Deployment

```dockerfile
FROM python:3.11-slim

WORKDIR /app

COPY requirements.txt .
RUN pip install -r requirements.txt

COPY mcp_server.py .

ENV API_BASE_URL=https://your-domain.com/api/v1
ENV API_KEY=your-api-key

CMD ["python", "mcp_server.py"]
```

### Docker Compose

```yaml
version: '3.8'

services:
  mcp-server:
    build: .
    environment:
      - API_BASE_URL=https://your-domain.com/api/v1
      - API_KEY=${API_KEY}
    ports:
      - "3000:3000"
    restart: unless-stopped
```

### Environment Variables

```bash
# .env file
API_BASE_URL=https://your-domain.com/api/v1
API_KEY=your-secure-api-key-here
MCP_SERVER_PORT=3000
LOG_LEVEL=info
```

### Systemd Service

```ini
[Unit]
Description=Learning Community MCP Server
After=network.target

[Service]
Type=simple
User=mcp-user
WorkingDirectory=/opt/mcp-server
ExecStart=/usr/bin/python3 /opt/mcp-server/mcp_server.py
Restart=on-failure
RestartSec=10

Environment="API_BASE_URL=https://your-domain.com/api/v1"
Environment="API_KEY=your-api-key"

[Install]
WantedBy=multi-user.target
```

## Error Handling

The MCP server should handle various error scenarios:

```python
class ErrorHandler:
    @staticmethod
    def handle_api_error(error):
        if error.response.status_code == 401:
            return {"error": "Authentication failed. Check your API key."}
        elif error.response.status_code == 404:
            return {"error": "Resource not found."}
        elif error.response.status_code == 429:
            return {"error": "Rate limit exceeded. Please try again later."}
        else:
            return {"error": f"API error: {error.response.text}"}
```

## Rate Limiting

The API implements rate limiting. The MCP server should respect these limits:

- 100 requests per minute per API key
- 1000 requests per hour per API key

Headers returned:
- `X-RateLimit-Limit`: Request limit
- `X-RateLimit-Remaining`: Remaining requests
- `X-RateLimit-Reset`: Reset timestamp

## Security Considerations

1. **API Key Storage**: Never hardcode API keys. Use environment variables or secure key management systems.

2. **HTTPS Only**: Always use HTTPS for API communication.

3. **Input Validation**: Validate all inputs before sending to the API.

4. **Error Messages**: Don't expose sensitive information in error messages.

5. **Logging**: Log requests but exclude sensitive data like API keys.

## Troubleshooting

### Common Issues

1. **Authentication Errors**
   - Verify API key is correct
   - Check key hasn't expired
   - Ensure proper header format

2. **Connection Issues**
   - Verify API base URL
   - Check network connectivity
   - Confirm firewall rules

3. **Data Format Errors**
   - Validate JSON structure
   - Check required fields
   - Verify data types

### Debug Mode

Enable debug logging:
```python
import logging
logging.basicConfig(level=logging.DEBUG)
```

## Support

For API-specific issues, contact: support@your-domain.com
For MCP protocol questions, refer to: https://modelcontextprotocol.io/docs