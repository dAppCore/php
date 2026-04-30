package php

import (
	"fmt"
	"io/fs"
	"os"
	"path/filepath"
)

// Extract copies an embedded Laravel app (from embed.FS) to a temporary directory.
// FrankenPHP needs real filesystem paths — it cannot serve from embed.FS.
// The prefix is the embed directory name (e.g. "laravel").
// Returns the path to the extracted Laravel root. Caller must os.RemoveAll on cleanup.
func Extract(fsys fs.FS, prefix string) (string, error) {
	tmpDir, err := os.MkdirTemp("", "go-php-laravel-*")
	if err != nil {
		return "", fmt.Errorf("create temp dir: %w", err)
	}

	err = fs.WalkDir(fsys, prefix, func(path string, d fs.DirEntry, err error) error {
		if err != nil {
			return err
		}

		relPath, err := filepath.Rel(prefix, path)
		if err != nil {
			return err
		}
		targetPath := filepath.Join(tmpDir, relPath)

		if d.IsDir() {
			return os.MkdirAll(targetPath, 0o755)
		}

		data, err := fs.ReadFile(fsys, path)
		if err != nil {
			return fmt.Errorf("read embedded %s: %w", path, err)
		}

		return os.WriteFile(targetPath, data, 0o644)
	})

	if err != nil {
		_ = os.RemoveAll(tmpDir)
		return "", fmt.Errorf("extract Laravel: %w", err)
	}

	return tmpDir, nil
}
