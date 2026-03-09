// Package main provides the core-php binary — a standalone PHP/Laravel
// development tool with FrankenPHP embedding support.
package main

import (
	php "forge.lthn.ai/core/php"

	"forge.lthn.ai/core/cli/pkg/cli"
)

func main() {
	cli.Main(
		cli.WithCommands("php", php.AddPHPRootCommands),
	)
}
