---
title: Tenant Package
navTitle: Tenant
navOrder: 10
---

# Tenant Package

Multi-tenancy, user management, and entitlement systems for the Host UK platform.

## Overview

The `core-tenant` package is the foundational tenancy layer that provides workspace isolation, user authentication, and feature access control.

## Core Concepts

### Tenant Hierarchy

```
User
├── owns Workspaces (can own multiple)
│   ├── has WorkspacePackages (entitlements)
│   ├── has Boosts (temporary limit increases)
│   ├── has Members (users with roles/permissions)
│   ├── has Teams (permission groups)
│   └── owns Namespaces (product boundaries)
└── owns Namespaces (personal, not workspace-linked)
```

## Features

- **Workspaces** - Primary tenant boundary (organisations, teams)
- **Namespaces** - Product-level isolation within or across workspaces
- **Entitlements** - Feature access control and usage limits
- **User management** - Authentication, 2FA, and membership
- **Teams** - Permission groups within workspaces

## Installation

```bash
composer require host-uk/core-tenant
```

## Documentation

- [Architecture](./architecture) - Technical architecture
- [Entitlements](./entitlements) - Feature access and limits
- [Security](./security) - Tenant isolation and security