---
title: Developer Package
navTitle: Developer
navOrder: 80
---

# Developer Package

Administrative developer tools for the Host UK platform.

## Overview

The `core-developer` package provides debugging, monitoring, and server management capabilities. It is designed exclusively for "Hades" tier users (god-mode access).

## Features

- **Server management** - SSH connections and remote commands
- **Route testing** - Automated route health checks
- **Debug tools** - Development debugging utilities
- **Horizon integration** - Queue monitoring
- **Telescope integration** - Request debugging

## Installation

```bash
composer require host-uk/core-developer
```

## Requirements

- Hades tier access (god-mode)
- `core-php` and `core-admin` packages

## Documentation

- [Architecture](./architecture) - Technical architecture
- [Security](./security) - Access control and authorisation