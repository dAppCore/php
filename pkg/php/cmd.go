package php

import (
	"os"
	"path/filepath"

	"dappco.re/go/cli/pkg/cli"
	"dappco.re/go/i18n"
	"dappco.re/go/io"
)

// DefaultMedium is the default filesystem medium used by the php package.
// It defaults to io.Local (unsandboxed filesystem access).
// Use SetMedium to change this for testing or sandboxed operation.
var DefaultMedium io.Medium = io.Local

// SetMedium sets the default medium for filesystem operations.
// This is primarily useful for testing with mock mediums.
func SetMedium(m io.Medium) {
	DefaultMedium = m
}

// getMedium returns the default medium for filesystem operations.
func getMedium() io.Medium {
	return DefaultMedium
}

// Style aliases from shared
var (
	successStyle = cli.SuccessStyle
	errorStyle   = cli.ErrorStyle
	dimStyle     = cli.DimStyle
	linkStyle    = cli.LinkStyle
)

// Service colors for log output (domain-specific, keep local)
var (
	phpFrankenPHPStyle = cli.NewStyle().Foreground(cli.ColourIndigo500)
	phpViteStyle       = cli.NewStyle().Foreground(cli.ColourYellow500)
	phpHorizonStyle    = cli.NewStyle().Foreground(cli.ColourOrange500)
	phpReverbStyle     = cli.NewStyle().Foreground(cli.ColourViolet500)
	phpRedisStyle      = cli.NewStyle().Foreground(cli.ColourRed500)
)

// Status styles (from shared)
var (
	phpStatusRunning = cli.SuccessStyle
	phpStatusStopped = cli.DimStyle
	phpStatusError   = cli.ErrorStyle
)

// QA command styles (from shared) — most moved to core/lint
var (
	phpQAWarningStyle = cli.WarningStyle
)

// AddPHPCommands adds PHP/Laravel development commands.
func AddPHPCommands(root *cli.Command) {
	phpCmd := &cli.Command{
		Use:   "php",
		Short: i18n.T("cmd.php.short"),
		Long:  i18n.T("cmd.php.long"),
		PersistentPreRunE: func(cmd *cli.Command, args []string) error {
			return activateWorkspacePackage()
		},
	}
	root.AddCommand(phpCmd)

	// Development
	addPHPDevCommand(phpCmd)
	addPHPLogsCommand(phpCmd)
	addPHPStopCommand(phpCmd)
	addPHPStatusCommand(phpCmd)
	addPHPSSLCommand(phpCmd)

	// Build & Deploy
	addPHPBuildCommand(phpCmd)
	addPHPServeCommand(phpCmd)
	addPHPShellCommand(phpCmd)

	// CI/CD Integration
	addPHPCICommand(phpCmd)

	// Package Management
	addPHPPackagesCommands(phpCmd)

	// Deployment
	addPHPDeployCommands(phpCmd)

	// FrankenPHP embedded commands (CGO only)
	if registerFrankenPHP != nil {
		registerFrankenPHP(phpCmd)
	}
}

// registerFrankenPHP is set by cmd_serve_frankenphp.go when CGO is enabled.
var registerFrankenPHP func(phpCmd *cli.Command)

// AddPHPRootCommands adds PHP commands directly to root (for standalone core-php binary).
func AddPHPRootCommands(root *cli.Command) {
	root.PersistentPreRunE = func(cmd *cli.Command, args []string) error {
		return activateWorkspacePackage()
	}

	// Development
	addPHPDevCommand(root)
	addPHPLogsCommand(root)
	addPHPStopCommand(root)
	addPHPStatusCommand(root)
	addPHPSSLCommand(root)

	// Build & Deploy
	addPHPBuildCommand(root)
	addPHPServeCommand(root)
	addPHPShellCommand(root)

	// CI/CD Integration
	addPHPCICommand(root)

	// Package Management
	addPHPPackagesCommands(root)

	// Deployment
	addPHPDeployCommands(root)

	// FrankenPHP embedded commands (CGO only)
	if registerFrankenPHP != nil {
		registerFrankenPHP(root)
	}
}

func activateWorkspacePackage() error {
	wsRoot, config, ok := loadActiveWorkspaceConfig()
	if !ok {
		return nil
	}

	targetDir := activeWorkspacePackageDir(wsRoot, config)
	if !getMedium().IsDir(targetDir) {
		cli.Warnf("Active package directory not found: %s", targetDir)
		return nil
	}

	if err := os.Chdir(targetDir); err != nil {
		return cli.Err("failed to change directory to active package: %w", err)
	}

	cli.Print(cliLabelValueFormat, dimStyle.Render("Workspace:"), config.Active)
	return nil
}

func loadActiveWorkspaceConfig() (string, *workspaceConfig, bool) {
	wsRoot, err := findWorkspaceRoot()
	if err != nil {
		return "", nil, false
	}

	config, err := loadWorkspaceConfig(wsRoot)
	if err != nil || config == nil || config.Active == "" {
		return "", nil, false
	}

	return wsRoot, config, true
}

func activeWorkspacePackageDir(wsRoot string, config *workspaceConfig) string {
	pkgDir := config.PackagesDir
	if pkgDir == "" {
		pkgDir = "./packages"
	}
	if !filepath.IsAbs(pkgDir) {
		pkgDir = filepath.Join(wsRoot, pkgDir)
	}

	return filepath.Join(pkgDir, config.Active)
}
