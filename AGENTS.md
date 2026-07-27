# Agent Instructions

This repository follows the core/go v0.9.0 consumer contract.

- Keep Go code under `go/`.
- Use `dappco.re/go` primitives instead of direct banned stdlib imports.
- Do not edit `.core/` runtime configuration.
- Do not edit `external/` dependency sources.
- Verify with `bash /Users/snider/Code/core/go/tests/cli/v090-upgrade/audit.sh .`.
