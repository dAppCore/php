---
title: Content Package
navTitle: Content
navOrder: 40
---

# Content Package

Headless CMS functionality for the Host UK platform.

## Overview

The `core-content` package provides content management, AI-powered generation, revision history, webhooks for external CMS integration, and search capabilities.

## Features

- **Content items** - Flexible content types with custom fields
- **AI generation** - Content creation via AI services
- **Revision history** - Full version control for content
- **Webhooks** - External CMS integration
- **Search** - Full-text search across content

## Installation

```bash
composer require host-uk/core-content
```

## Dependencies

- `core-php` - Foundation framework
- `core-tenant` - Workspaces and users
- Optional: `core-agentic` - AI content generation
- Optional: `core-mcp` - MCP tool handlers

## Documentation

- [Architecture](./architecture) - Technical architecture
- [Security](./security) - Content security model