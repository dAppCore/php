//go:build cgo

package php

import (
	"crypto/rand"
	"encoding/base64"
	"fmt"
	"log"
	"os"
	"path/filepath"
	"runtime"
)

// RuntimeEnvironment holds the resolved paths for the running application.
type RuntimeEnvironment struct {
	// DataDir is the persistent data directory (survives app updates).
	DataDir string
	// LaravelRoot is the extracted Laravel app in the temp directory.
	LaravelRoot string
	// DatabasePath is the full path to the SQLite database file.
	DatabasePath string
}

// PrepareRuntimeEnvironment creates data directories, generates .env, and symlinks
// storage so Laravel can write to persistent locations.
// The appName is used for the data directory name (e.g. "bugseti").
func PrepareRuntimeEnvironment(laravelRoot, appName string) (*RuntimeEnvironment, error) {
	dataDir, err := resolveDataDir(appName)
	if err != nil {
		return nil, fmt.Errorf("resolve data dir: %w", err)
	}

	env := &RuntimeEnvironment{
		DataDir:      dataDir,
		LaravelRoot:  laravelRoot,
		DatabasePath: filepath.Join(dataDir, appName+".sqlite"),
	}

	// Create persistent directories
	dirs := []string{
		dataDir,
		filepath.Join(dataDir, "storage", "app"),
		filepath.Join(dataDir, "storage", "framework", "cache", "data"),
		filepath.Join(dataDir, "storage", "framework", "sessions"),
		filepath.Join(dataDir, "storage", "framework", "views"),
		filepath.Join(dataDir, "storage", "logs"),
	}
	for _, dir := range dirs {
		if err := os.MkdirAll(dir, 0o755); err != nil {
			return nil, fmt.Errorf("create dir %s: %w", dir, err)
		}
	}

	// Create empty SQLite database if it doesn't exist
	if _, err := os.Stat(env.DatabasePath); os.IsNotExist(err) {
		if err := os.WriteFile(env.DatabasePath, nil, 0o644); err != nil {
			return nil, fmt.Errorf("create database: %w", err)
		}
		log.Printf("go-php: created new database: %s", env.DatabasePath)
	}

	// Replace the extracted storage/ with a symlink to the persistent one
	extractedStorage := filepath.Join(laravelRoot, "storage")
	os.RemoveAll(extractedStorage)
	persistentStorage := filepath.Join(dataDir, "storage")
	if err := os.Symlink(persistentStorage, extractedStorage); err != nil {
		return nil, fmt.Errorf("symlink storage: %w", err)
	}

	// Generate .env file with resolved paths
	if err := writeEnvFile(laravelRoot, appName, env); err != nil {
		return nil, fmt.Errorf("write .env: %w", err)
	}

	return env, nil
}

// AppendEnv appends a key=value pair to the Laravel .env file.
func AppendEnv(laravelRoot, key, value string) error {
	envFile := filepath.Join(laravelRoot, ".env")
	f, err := os.OpenFile(envFile, os.O_APPEND|os.O_WRONLY, 0o644)
	if err != nil {
		return err
	}
	defer f.Close()
	_, err = fmt.Fprintf(f, "%s=\"%s\"\n", key, value)
	return err
}

func resolveDataDir(appName string) (string, error) {
	var base string
	switch runtime.GOOS {
	case "darwin":
		home, err := os.UserHomeDir()
		if err != nil {
			return "", err
		}
		base = filepath.Join(home, "Library", "Application Support", appName)
	case "linux":
		if xdg := os.Getenv("XDG_DATA_HOME"); xdg != "" {
			base = filepath.Join(xdg, appName)
		} else {
			home, err := os.UserHomeDir()
			if err != nil {
				return "", err
			}
			base = filepath.Join(home, ".local", "share", appName)
		}
	default:
		home, err := os.UserHomeDir()
		if err != nil {
			return "", err
		}
		base = filepath.Join(home, "."+appName)
	}
	return base, nil
}

func writeEnvFile(laravelRoot, appName string, env *RuntimeEnvironment) error {
	appKey, err := loadOrGenerateAppKey(env.DataDir)
	if err != nil {
		return fmt.Errorf("app key: %w", err)
	}

	content := fmt.Sprintf(`APP_NAME="%s"
APP_ENV=production
APP_KEY=%s
APP_DEBUG=false
APP_URL=http://localhost

DB_CONNECTION=sqlite
DB_DATABASE="%s"

CACHE_STORE=file
SESSION_DRIVER=file
LOG_CHANNEL=single
LOG_LEVEL=warning

`, appName, appKey, env.DatabasePath)

	return os.WriteFile(filepath.Join(laravelRoot, ".env"), []byte(content), 0o644)
}

func loadOrGenerateAppKey(dataDir string) (string, error) {
	keyFile := filepath.Join(dataDir, ".app-key")

	data, err := os.ReadFile(keyFile)
	if err == nil && len(data) > 0 {
		return string(data), nil
	}

	key := make([]byte, 32)
	if _, err := rand.Read(key); err != nil {
		return "", fmt.Errorf("generate key: %w", err)
	}
	appKey := "base64:" + base64.StdEncoding.EncodeToString(key)

	if err := os.WriteFile(keyFile, []byte(appKey), 0o600); err != nil {
		return "", fmt.Errorf("save key: %w", err)
	}

	log.Printf("go-php: generated new APP_KEY (saved to %s)", keyFile)
	return appKey, nil
}
