package php

import (
	"fmt"
	"os"
	"path/filepath"

	"dappco.re/go/io"
	"gopkg.in/yaml.v3"
)

// workspaceConfig holds workspace-level configuration from .core/workspace.yaml.
type workspaceConfig struct {
	Version     int      `yaml:"version"`
	Active      string   `yaml:"active"`       // Active package name
	DefaultOnly []string `yaml:"default_only"` // Default types for setup
	PackagesDir string   `yaml:"packages_dir"` // Where packages are cloned
}

// defaultWorkspaceConfig returns a config with default values.
func defaultWorkspaceConfig() *workspaceConfig {
	return &workspaceConfig{
		Version:     1,
		PackagesDir: "./packages",
	}
}

// loadWorkspaceConfig tries to load workspace.yaml from the given directory's .core subfolder.
// Returns nil if no config file exists.
func loadWorkspaceConfig(dir string) (*workspaceConfig, error) {
	path := filepath.Join(dir, ".core", "workspace.yaml")
	data, err := io.Local.Read(path)
	if err != nil {
		if !io.Local.IsFile(path) {
			parent := filepath.Dir(dir)
			if parent != dir {
				return loadWorkspaceConfig(parent)
			}
			return nil, nil
		}
		return nil, fmt.Errorf("failed to read workspace config: %w", err)
	}

	config := defaultWorkspaceConfig()
	if err := yaml.Unmarshal([]byte(data), config); err != nil {
		return nil, fmt.Errorf("failed to parse workspace config: %w", err)
	}

	if config.Version != 1 {
		return nil, fmt.Errorf("unsupported workspace config version: %d", config.Version)
	}

	return config, nil
}

// findWorkspaceRoot searches for the root directory containing .core/workspace.yaml.
func findWorkspaceRoot() (string, error) {
	dir, err := os.Getwd()
	if err != nil {
		return "", err
	}

	for {
		if io.Local.IsFile(filepath.Join(dir, ".core", "workspace.yaml")) {
			return dir, nil
		}

		parent := filepath.Dir(dir)
		if parent == dir {
			break
		}
		dir = parent
	}

	return "", fmt.Errorf("not in a workspace")
}
