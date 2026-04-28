package php

import (
	"os"
	"path/filepath"
)

func TestPHP_IsLaravelProject_Good(t *T) {
	t.Run("valid Laravel project with artisan and composer.json", func(t *T) {
		dir := t.TempDir()

		// Create artisan file
		artisanPath := filepath.Join(dir, "artisan")
		err := os.WriteFile(artisanPath, []byte("#!/usr/bin/env php\n"), 0755)
		RequireNoError(t, err)

		// Create composer.json with laravel/framework
		composerJSON := `{
			"name": "test/laravel-project",
			"require": {
				"php": "^8.2",
				"laravel/framework": "^11.0"
			}
		}`
		composerPath := filepath.Join(dir, "composer.json")
		err = os.WriteFile(composerPath, []byte(composerJSON), 0644)
		RequireNoError(t, err)

		AssertTrue(t, IsLaravelProject(dir))
	})

	t.Run("Laravel in require-dev", func(t *T) {
		dir := t.TempDir()

		// Create artisan file
		artisanPath := filepath.Join(dir, "artisan")
		err := os.WriteFile(artisanPath, []byte("#!/usr/bin/env php\n"), 0755)
		RequireNoError(t, err)

		// Create composer.json with laravel/framework in require-dev
		composerJSON := `{
			"name": "test/laravel-project",
			"require-dev": {
				"laravel/framework": "^11.0"
			}
		}`
		composerPath := filepath.Join(dir, "composer.json")
		err = os.WriteFile(composerPath, []byte(composerJSON), 0644)
		RequireNoError(t, err)

		AssertTrue(t, IsLaravelProject(dir))
	})
}

func TestPHP_IsLaravelProject_Bad(t *T) {
	t.Run("missing artisan file", func(t *T) {
		dir := t.TempDir()

		// Create composer.json but no artisan
		composerJSON := `{
			"name": "test/laravel-project",
			"require": {
				"laravel/framework": "^11.0"
			}
		}`
		composerPath := filepath.Join(dir, "composer.json")
		err := os.WriteFile(composerPath, []byte(composerJSON), 0644)
		RequireNoError(t, err)

		AssertFalse(t, IsLaravelProject(dir))
	})

	t.Run("missing composer.json", func(t *T) {
		dir := t.TempDir()

		// Create artisan but no composer.json
		artisanPath := filepath.Join(dir, "artisan")
		err := os.WriteFile(artisanPath, []byte("#!/usr/bin/env php\n"), 0755)
		RequireNoError(t, err)

		AssertFalse(t, IsLaravelProject(dir))
	})

	t.Run("composer.json without Laravel", func(t *T) {
		dir := t.TempDir()

		// Create artisan file
		artisanPath := filepath.Join(dir, "artisan")
		err := os.WriteFile(artisanPath, []byte("#!/usr/bin/env php\n"), 0755)
		RequireNoError(t, err)

		// Create composer.json without laravel/framework
		composerJSON := `{
			"name": "test/symfony-project",
			"require": {
				"symfony/framework-bundle": "^7.0"
			}
		}`
		composerPath := filepath.Join(dir, "composer.json")
		err = os.WriteFile(composerPath, []byte(composerJSON), 0644)
		RequireNoError(t, err)

		AssertFalse(t, IsLaravelProject(dir))
	})

	t.Run("invalid composer.json", func(t *T) {
		dir := t.TempDir()

		// Create artisan file
		artisanPath := filepath.Join(dir, "artisan")
		err := os.WriteFile(artisanPath, []byte("#!/usr/bin/env php\n"), 0755)
		RequireNoError(t, err)

		// Create invalid composer.json
		composerPath := filepath.Join(dir, "composer.json")
		err = os.WriteFile(composerPath, []byte("not valid json{"), 0644)
		RequireNoError(t, err)

		AssertFalse(t, IsLaravelProject(dir))
	})

	t.Run("empty directory", func(t *T) {
		dir := t.TempDir()
		AssertFalse(t, IsLaravelProject(dir))
	})

	t.Run("non-existent directory", func(t *T) {
		AssertFalse(t, IsLaravelProject("/non/existent/path"))
	})
}

func TestPHP_IsFrankenPHPProject_Good(t *T) {
	t.Run("project with octane and frankenphp config", func(t *T) {
		dir := t.TempDir()

		// Create composer.json with laravel/octane
		composerJSON := `{
			"require": {
				"laravel/octane": "^2.0"
			}
		}`
		err := os.WriteFile(filepath.Join(dir, "composer.json"), []byte(composerJSON), 0644)
		RequireNoError(t, err)

		// Create config directory and octane.php
		configDir := filepath.Join(dir, "config")
		err = os.MkdirAll(configDir, 0755)
		RequireNoError(t, err)

		octaneConfig := `<?php
return [
    'server' => 'frankenphp',
];`
		err = os.WriteFile(filepath.Join(configDir, "octane.php"), []byte(octaneConfig), 0644)
		RequireNoError(t, err)

		AssertTrue(t, IsFrankenPHPProject(dir))
	})

	t.Run("project with octane but no config file", func(t *T) {
		dir := t.TempDir()

		// Create composer.json with laravel/octane
		composerJSON := `{
			"require": {
				"laravel/octane": "^2.0"
			}
		}`
		err := os.WriteFile(filepath.Join(dir, "composer.json"), []byte(composerJSON), 0644)
		RequireNoError(t, err)

		// No config file - should still return true (assume frankenphp)
		AssertTrue(t, IsFrankenPHPProject(dir))
	})

	t.Run("project with octane but unreadable config file", func(t *T) {
		if os.Geteuid() == 0 {
			t.Skip("root can read any file")
		}
		dir := t.TempDir()

		// Create composer.json with laravel/octane
		composerJSON := `{
			"require": {
				"laravel/octane": "^2.0"
			}
		}`
		err := os.WriteFile(filepath.Join(dir, "composer.json"), []byte(composerJSON), 0644)
		RequireNoError(t, err)

		// Create config directory and octane.php with no read permissions
		configDir := filepath.Join(dir, "config")
		err = os.MkdirAll(configDir, 0755)
		RequireNoError(t, err)

		octanePath := filepath.Join(configDir, "octane.php")
		err = os.WriteFile(octanePath, []byte("<?php return [];"), 0000)
		RequireNoError(t, err)
		defer func() { _ = os.Chmod(octanePath, 0644) }() // Clean up

		// Should return true (assume frankenphp if unreadable)
		AssertTrue(t, IsFrankenPHPProject(dir))
	})
}

func TestPHP_IsFrankenPHPProject_Bad(t *T) {
	t.Run("project without octane", func(t *T) {
		dir := t.TempDir()

		composerJSON := `{
			"require": {
				"laravel/framework": "^11.0"
			}
		}`
		err := os.WriteFile(filepath.Join(dir, "composer.json"), []byte(composerJSON), 0644)
		RequireNoError(t, err)

		AssertFalse(t, IsFrankenPHPProject(dir))
	})

	t.Run("missing composer.json", func(t *T) {
		dir := t.TempDir()
		AssertFalse(t, IsFrankenPHPProject(dir))
	})
}

func TestPHP_DetectServices_Good(t *T) {
	t.Run("full Laravel project with all services", func(t *T) {
		dir := t.TempDir()

		// Setup Laravel project
		err := os.WriteFile(filepath.Join(dir, "artisan"), []byte("#!/usr/bin/env php\n"), 0755)
		RequireNoError(t, err)

		composerJSON := `{
			"require": {
				"laravel/framework": "^11.0",
				"laravel/octane": "^2.0"
			}
		}`
		err = os.WriteFile(filepath.Join(dir, "composer.json"), []byte(composerJSON), 0644)
		RequireNoError(t, err)

		// Add vite.config.js
		err = os.WriteFile(filepath.Join(dir, "vite.config.js"), []byte("export default {}"), 0644)
		RequireNoError(t, err)

		// Add config directory
		configDir := filepath.Join(dir, "config")
		err = os.MkdirAll(configDir, 0755)
		RequireNoError(t, err)

		// Add horizon.php
		err = os.WriteFile(filepath.Join(configDir, "horizon.php"), []byte("<?php return [];"), 0644)
		RequireNoError(t, err)

		// Add reverb.php
		err = os.WriteFile(filepath.Join(configDir, "reverb.php"), []byte("<?php return [];"), 0644)
		RequireNoError(t, err)

		// Add .env with Redis
		envContent := `APP_NAME=TestApp
CACHE_DRIVER=redis
REDIS_HOST=127.0.0.1`
		err = os.WriteFile(filepath.Join(dir, ".env"), []byte(envContent), 0644)
		RequireNoError(t, err)

		services := DetectServices(dir)

		AssertContains(t, services, ServiceFrankenPHP)
		AssertContains(t, services, ServiceVite)
		AssertContains(t, services, ServiceHorizon)
		AssertContains(t, services, ServiceReverb)
		AssertContains(t, services, ServiceRedis)
	})

	t.Run("minimal Laravel project", func(t *T) {
		dir := t.TempDir()

		// Setup minimal Laravel project
		err := os.WriteFile(filepath.Join(dir, "artisan"), []byte("#!/usr/bin/env php\n"), 0755)
		RequireNoError(t, err)

		composerJSON := `{
			"require": {
				"laravel/framework": "^11.0"
			}
		}`
		err = os.WriteFile(filepath.Join(dir, "composer.json"), []byte(composerJSON), 0644)
		RequireNoError(t, err)

		services := DetectServices(dir)

		AssertContains(t, services, ServiceFrankenPHP)
		AssertNotContains(t, services, ServiceVite)
		AssertNotContains(t, services, ServiceHorizon)
		AssertNotContains(t, services, ServiceReverb)
		AssertNotContains(t, services, ServiceRedis)
	})
}

func TestPHP_HasHorizon_Good(t *T) {
	t.Run("horizon config exists", func(t *T) {
		dir := t.TempDir()
		configDir := filepath.Join(dir, "config")
		err := os.MkdirAll(configDir, 0755)
		RequireNoError(t, err)

		err = os.WriteFile(filepath.Join(configDir, "horizon.php"), []byte("<?php return [];"), 0644)
		RequireNoError(t, err)

		AssertTrue(t, hasHorizon(dir))
	})
}

func TestPHP_HasHorizon_Bad(t *T) {
	t.Run("horizon config missing", func(t *T) {
		dir := t.TempDir()
		AssertFalse(t, hasHorizon(dir))
	})
}

func TestPHP_HasReverb_Good(t *T) {
	t.Run("reverb config exists", func(t *T) {
		dir := t.TempDir()
		configDir := filepath.Join(dir, "config")
		err := os.MkdirAll(configDir, 0755)
		RequireNoError(t, err)

		err = os.WriteFile(filepath.Join(configDir, "reverb.php"), []byte("<?php return [];"), 0644)
		RequireNoError(t, err)

		AssertTrue(t, hasReverb(dir))
	})
}

func TestPHP_HasReverb_Bad(t *T) {
	t.Run("reverb config missing", func(t *T) {
		dir := t.TempDir()
		AssertFalse(t, hasReverb(dir))
	})
}

func TestPHP_DetectServices_Bad(t *T) {
	t.Run("non-Laravel project", func(t *T) {
		dir := t.TempDir()

		services := DetectServices(dir)
		AssertEmpty(t, services)
	})
}

func TestPHP_DetectPackageManager_Good(t *T) {
	tests := []struct {
		name     string
		lockFile string
		expected string
	}{
		{"bun detected", "bun.lockb", "bun"},
		{"pnpm detected", "pnpm-lock.yaml", "pnpm"},
		{"yarn detected", "yarn.lock", "yarn"},
		{"npm detected", "package-lock.json", "npm"},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *T) {
			dir := t.TempDir()

			err := os.WriteFile(filepath.Join(dir, tt.lockFile), []byte(""), 0644)
			RequireNoError(t, err)

			result := DetectPackageManager(dir)
			AssertEqual(t, tt.expected, result)
		})
	}

	t.Run("no lock file defaults to npm", func(t *T) {
		dir := t.TempDir()

		result := DetectPackageManager(dir)
		AssertEqual(t, "npm", result)
	})

	t.Run("bun takes priority over npm", func(t *T) {
		dir := t.TempDir()

		// Create both lock files
		err := os.WriteFile(filepath.Join(dir, "bun.lockb"), []byte(""), 0644)
		RequireNoError(t, err)
		err = os.WriteFile(filepath.Join(dir, "package-lock.json"), []byte(""), 0644)
		RequireNoError(t, err)

		result := DetectPackageManager(dir)
		AssertEqual(t, "bun", result)
	})
}

func TestPHP_GetLaravelAppName_Good(t *T) {
	t.Run("simple app name", func(t *T) {
		dir := t.TempDir()

		envContent := `APP_NAME=MyApp
APP_ENV=local`
		err := os.WriteFile(filepath.Join(dir, ".env"), []byte(envContent), 0644)
		RequireNoError(t, err)

		AssertEqual(t, "MyApp", GetLaravelAppName(dir))
	})

	t.Run("quoted app name", func(t *T) {
		dir := t.TempDir()

		envContent := `APP_NAME="My Awesome App"
APP_ENV=local`
		err := os.WriteFile(filepath.Join(dir, ".env"), []byte(envContent), 0644)
		RequireNoError(t, err)

		AssertEqual(t, "My Awesome App", GetLaravelAppName(dir))
	})

	t.Run("single quoted app name", func(t *T) {
		dir := t.TempDir()

		envContent := `APP_NAME='My App'
APP_ENV=local`
		err := os.WriteFile(filepath.Join(dir, ".env"), []byte(envContent), 0644)
		RequireNoError(t, err)

		AssertEqual(t, "My App", GetLaravelAppName(dir))
	})
}

func TestPHP_GetLaravelAppName_Bad(t *T) {
	t.Run("no .env file", func(t *T) {
		dir := t.TempDir()
		AssertEqual(t, "", GetLaravelAppName(dir))
	})

	t.Run("no APP_NAME in .env", func(t *T) {
		dir := t.TempDir()

		envContent := `APP_ENV=local
APP_DEBUG=true`
		err := os.WriteFile(filepath.Join(dir, ".env"), []byte(envContent), 0644)
		RequireNoError(t, err)

		AssertEqual(t, "", GetLaravelAppName(dir))
	})
}

func TestPHP_GetLaravelAppURL_Good(t *T) {
	t.Run("standard URL", func(t *T) {
		dir := t.TempDir()

		envContent := `APP_NAME=MyApp
APP_URL=https://myapp.test`
		err := os.WriteFile(filepath.Join(dir, ".env"), []byte(envContent), 0644)
		RequireNoError(t, err)

		AssertEqual(t, "https://myapp.test", GetLaravelAppURL(dir))
	})

	t.Run("quoted URL", func(t *T) {
		dir := t.TempDir()

		envContent := `APP_URL="http://localhost:8000"`
		err := os.WriteFile(filepath.Join(dir, ".env"), []byte(envContent), 0644)
		RequireNoError(t, err)

		AssertEqual(t, "http://localhost:8000", GetLaravelAppURL(dir))
	})
}

func TestPHP_ExtractDomainFromURL_Good(t *T) {
	tests := []struct {
		url      string
		expected string
	}{
		{"https://example.com", "example.com"},
		{"http://example.com", "example.com"},
		{"https://example.com:8080", "example.com"},
		{"https://example.com/path/to/page", "example.com"},
		{"https://example.com:443/path", "example.com"},
		{"localhost", "localhost"},
		{"localhost:8000", "localhost"},
	}

	for _, tt := range tests {
		t.Run(tt.url, func(t *T) {
			result := ExtractDomainFromURL(tt.url)
			AssertEqual(t, tt.expected, result)
		})
	}
}

func TestPHP_NeedsRedis_Good(t *T) {
	t.Run("CACHE_DRIVER=redis", func(t *T) {
		dir := t.TempDir()

		envContent := `APP_NAME=Test
CACHE_DRIVER=redis`
		err := os.WriteFile(filepath.Join(dir, ".env"), []byte(envContent), 0644)
		RequireNoError(t, err)

		AssertTrue(t, needsRedis(dir))
	})

	t.Run("QUEUE_CONNECTION=redis", func(t *T) {
		dir := t.TempDir()

		envContent := `APP_NAME=Test
QUEUE_CONNECTION=redis`
		err := os.WriteFile(filepath.Join(dir, ".env"), []byte(envContent), 0644)
		RequireNoError(t, err)

		AssertTrue(t, needsRedis(dir))
	})

	t.Run("REDIS_HOST localhost", func(t *T) {
		dir := t.TempDir()

		envContent := `APP_NAME=Test
REDIS_HOST=localhost`
		err := os.WriteFile(filepath.Join(dir, ".env"), []byte(envContent), 0644)
		RequireNoError(t, err)

		AssertTrue(t, needsRedis(dir))
	})

	t.Run("REDIS_HOST 127.0.0.1", func(t *T) {
		dir := t.TempDir()

		envContent := `APP_NAME=Test
REDIS_HOST=127.0.0.1`
		err := os.WriteFile(filepath.Join(dir, ".env"), []byte(envContent), 0644)
		RequireNoError(t, err)

		AssertTrue(t, needsRedis(dir))
	})

	t.Run("SESSION_DRIVER=redis", func(t *T) {
		dir := t.TempDir()
		envContent := "SESSION_DRIVER=redis"
		err := os.WriteFile(filepath.Join(dir, ".env"), []byte(envContent), 0644)
		RequireNoError(t, err)
		AssertTrue(t, needsRedis(dir))
	})

	t.Run("BROADCAST_DRIVER=redis", func(t *T) {
		dir := t.TempDir()
		envContent := "BROADCAST_DRIVER=redis"
		err := os.WriteFile(filepath.Join(dir, ".env"), []byte(envContent), 0644)
		RequireNoError(t, err)
		AssertTrue(t, needsRedis(dir))
	})

	t.Run("REDIS_HOST remote (should be false for local dev env)", func(t *T) {
		dir := t.TempDir()
		envContent := "REDIS_HOST=redis.example.com"
		err := os.WriteFile(filepath.Join(dir, ".env"), []byte(envContent), 0644)
		RequireNoError(t, err)
		AssertFalse(t, needsRedis(dir))
	})
}

func TestPHP_NeedsRedis_Bad(t *T) {
	t.Run("no .env file", func(t *T) {
		dir := t.TempDir()
		AssertFalse(t, needsRedis(dir))
	})

	t.Run("no redis configuration", func(t *T) {
		dir := t.TempDir()

		envContent := `APP_NAME=Test
CACHE_DRIVER=file
QUEUE_CONNECTION=sync`
		err := os.WriteFile(filepath.Join(dir, ".env"), []byte(envContent), 0644)
		RequireNoError(t, err)

		AssertFalse(t, needsRedis(dir))
	})

	t.Run("commented redis config", func(t *T) {
		dir := t.TempDir()

		envContent := `APP_NAME=Test
# CACHE_DRIVER=redis`
		err := os.WriteFile(filepath.Join(dir, ".env"), []byte(envContent), 0644)
		RequireNoError(t, err)

		AssertFalse(t, needsRedis(dir))
	})
}

func TestPHP_HasVite_Good(t *T) {
	viteFiles := []string{
		"vite.config.js",
		"vite.config.ts",
		"vite.config.mjs",
		"vite.config.mts",
	}

	for _, file := range viteFiles {
		t.Run(file, func(t *T) {
			dir := t.TempDir()

			err := os.WriteFile(filepath.Join(dir, file), []byte("export default {}"), 0644)
			RequireNoError(t, err)

			AssertTrue(t, hasVite(dir))
		})
	}
}

func TestPHP_HasVite_Bad(t *T) {
	t.Run("no vite config", func(t *T) {
		dir := t.TempDir()
		AssertFalse(t, hasVite(dir))
	})

	t.Run("wrong file name", func(t *T) {
		dir := t.TempDir()

		err := os.WriteFile(filepath.Join(dir, "vite.config.json"), []byte("{}"), 0644)
		RequireNoError(t, err)

		AssertFalse(t, hasVite(dir))
	})
}

func TestIsFrankenPHPProject_ConfigWithoutFrankenPHP(t *T) {
	t.Run("octane config without frankenphp", func(t *T) {
		dir := t.TempDir()

		// Create composer.json with laravel/octane
		composerJSON := `{
			"require": {
				"laravel/octane": "^2.0"
			}
		}`
		err := os.WriteFile(filepath.Join(dir, "composer.json"), []byte(composerJSON), 0644)
		RequireNoError(t, err)

		// Create config directory and octane.php without frankenphp
		configDir := filepath.Join(dir, "config")
		err = os.MkdirAll(configDir, 0755)
		RequireNoError(t, err)

		octaneConfig := `<?php
return [
    'server' => 'swoole',
];`
		err = os.WriteFile(filepath.Join(configDir, "octane.php"), []byte(octaneConfig), 0644)
		RequireNoError(t, err)

		AssertFalse(t, IsFrankenPHPProject(dir))
	})
}
