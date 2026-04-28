package php

import (
	"os"
	"path/filepath"
	"strings"
)

func TestPHP_GenerateDockerfile_Good(t *T) {
	t.Run("basic Laravel project", func(t *T) {
		dir := t.TempDir()

		// Create composer.json
		composerJSON := `{
			"name": "test/laravel-project",
			"require": {
				"php": "^8.2",
				"laravel/framework": "^11.0"
			}
		}`
		err := os.WriteFile(filepath.Join(dir, "composer.json"), []byte(composerJSON), 0644)
		RequireNoError(t, err)

		// Create composer.lock
		err = os.WriteFile(filepath.Join(dir, "composer.lock"), []byte("{}"), 0644)
		RequireNoError(t, err)

		content, err := GenerateDockerfile(dir)
		RequireNoError(t, err)

		// Check content
		AssertContains(t, content, "FROM dunglas/frankenphp")
		AssertContains(t, content, "php8.2")
		AssertContains(t, content, "COPY composer.json composer.lock")
		AssertContains(t, content, "composer install")
		AssertContains(t, content, "EXPOSE 80 443")
	})

	t.Run("Laravel project with Octane", func(t *T) {
		dir := t.TempDir()

		composerJSON := `{
			"name": "test/laravel-octane",
			"require": {
				"php": "^8.3",
				"laravel/framework": "^11.0",
				"laravel/octane": "^2.0"
			}
		}`
		err := os.WriteFile(filepath.Join(dir, "composer.json"), []byte(composerJSON), 0644)
		RequireNoError(t, err)
		err = os.WriteFile(filepath.Join(dir, "composer.lock"), []byte("{}"), 0644)
		RequireNoError(t, err)

		content, err := GenerateDockerfile(dir)
		RequireNoError(t, err)

		AssertContains(t, content, "php8.3")
		AssertContains(t, content, "octane:start")
	})

	t.Run("project with frontend assets", func(t *T) {
		dir := t.TempDir()

		composerJSON := `{
			"name": "test/laravel-vite",
			"require": {
				"php": "^8.3",
				"laravel/framework": "^11.0"
			}
		}`
		err := os.WriteFile(filepath.Join(dir, "composer.json"), []byte(composerJSON), 0644)
		RequireNoError(t, err)
		err = os.WriteFile(filepath.Join(dir, "composer.lock"), []byte("{}"), 0644)
		RequireNoError(t, err)

		packageJSON := `{
			"name": "test-app",
			"scripts": {
				"dev": "vite",
				"build": "vite build"
			}
		}`
		err = os.WriteFile(filepath.Join(dir, "package.json"), []byte(packageJSON), 0644)
		RequireNoError(t, err)
		err = os.WriteFile(filepath.Join(dir, "package-lock.json"), []byte("{}"), 0644)
		RequireNoError(t, err)

		content, err := GenerateDockerfile(dir)
		RequireNoError(t, err)

		// Should have multi-stage build
		AssertContains(t, content, "FROM node:20-alpine AS frontend")
		AssertContains(t, content, "npm ci")
		AssertContains(t, content, "npm run build")
		AssertContains(t, content, "COPY --from=frontend")
	})

	t.Run("project with pnpm", func(t *T) {
		dir := t.TempDir()

		composerJSON := `{
			"name": "test/laravel-pnpm",
			"require": {
				"php": "^8.3",
				"laravel/framework": "^11.0"
			}
		}`
		err := os.WriteFile(filepath.Join(dir, "composer.json"), []byte(composerJSON), 0644)
		RequireNoError(t, err)
		err = os.WriteFile(filepath.Join(dir, "composer.lock"), []byte("{}"), 0644)
		RequireNoError(t, err)

		packageJSON := `{
			"name": "test-app",
			"scripts": {
				"build": "vite build"
			}
		}`
		err = os.WriteFile(filepath.Join(dir, "package.json"), []byte(packageJSON), 0644)
		RequireNoError(t, err)

		// Create pnpm-lock.yaml
		err = os.WriteFile(filepath.Join(dir, "pnpm-lock.yaml"), []byte("lockfileVersion: 6.0"), 0644)
		RequireNoError(t, err)

		content, err := GenerateDockerfile(dir)
		RequireNoError(t, err)

		AssertContains(t, content, "pnpm install")
		AssertContains(t, content, "pnpm run build")
	})

	t.Run("project with Redis dependency", func(t *T) {
		dir := t.TempDir()

		composerJSON := `{
			"name": "test/laravel-redis",
			"require": {
				"php": "^8.3",
				"laravel/framework": "^11.0",
				"predis/predis": "^2.0"
			}
		}`
		err := os.WriteFile(filepath.Join(dir, "composer.json"), []byte(composerJSON), 0644)
		RequireNoError(t, err)
		err = os.WriteFile(filepath.Join(dir, "composer.lock"), []byte("{}"), 0644)
		RequireNoError(t, err)

		content, err := GenerateDockerfile(dir)
		RequireNoError(t, err)

		AssertContains(t, content, "install-php-extensions")
		AssertContains(t, content, "redis")
	})

	t.Run("project with explicit ext- requirements", func(t *T) {
		dir := t.TempDir()

		composerJSON := `{
			"name": "test/with-extensions",
			"require": {
				"php": "^8.3",
				"ext-gd": "*",
				"ext-imagick": "*",
				"ext-intl": "*"
			}
		}`
		err := os.WriteFile(filepath.Join(dir, "composer.json"), []byte(composerJSON), 0644)
		RequireNoError(t, err)
		err = os.WriteFile(filepath.Join(dir, "composer.lock"), []byte("{}"), 0644)
		RequireNoError(t, err)

		content, err := GenerateDockerfile(dir)
		RequireNoError(t, err)

		AssertContains(t, content, "install-php-extensions")
		AssertContains(t, content, "gd")
		AssertContains(t, content, "imagick")
		AssertContains(t, content, "intl")
	})
}

func TestPHP_GenerateDockerfile_Bad(t *T) {
	t.Run("missing composer.json", func(t *T) {
		dir := t.TempDir()

		_, err := GenerateDockerfile(dir)
		AssertError(t, err)
		AssertContains(t, err.Error(), "composer.json")
	})

	t.Run("invalid composer.json", func(t *T) {
		dir := t.TempDir()

		err := os.WriteFile(filepath.Join(dir, "composer.json"), []byte("not json{"), 0644)
		RequireNoError(t, err)

		_, err = GenerateDockerfile(dir)
		AssertError(t, err)
	})
}

func TestPHP_DetectDockerfileConfig_Good(t *T) {
	t.Run("full Laravel project", func(t *T) {
		dir := t.TempDir()

		composerJSON := `{
			"name": "test/full-laravel",
			"require": {
				"php": "^8.3",
				"laravel/framework": "^11.0",
				"laravel/octane": "^2.0",
				"predis/predis": "^2.0",
				"intervention/image": "^3.0"
			}
		}`
		err := os.WriteFile(filepath.Join(dir, "composer.json"), []byte(composerJSON), 0644)
		RequireNoError(t, err)

		packageJSON := `{"scripts": {"build": "vite build"}}`
		err = os.WriteFile(filepath.Join(dir, "package.json"), []byte(packageJSON), 0644)
		RequireNoError(t, err)
		err = os.WriteFile(filepath.Join(dir, "yarn.lock"), []byte(""), 0644)
		RequireNoError(t, err)

		config, err := DetectDockerfileConfig(dir)
		RequireNoError(t, err)

		AssertEqual(t, "8.3", config.PHPVersion)
		AssertTrue(t, config.IsLaravel)
		AssertTrue(t, config.HasOctane)
		AssertTrue(t, config.HasAssets)
		AssertEqual(t, "yarn", config.PackageManager)
		AssertContains(t, config.PHPExtensions, "redis")
		AssertContains(t, config.PHPExtensions, "gd")
	})
}

func TestPHP_DetectDockerfileConfig_Bad(t *T) {
	t.Run("non-existent directory", func(t *T) {
		_, err := DetectDockerfileConfig("/non/existent/path")
		AssertError(t, err)
	})
}

func TestPHP_ExtractPHPVersion_Good(t *T) {
	tests := []struct {
		constraint string
		expected   string
	}{
		{"^8.2", "8.2"},
		{"^8.3", "8.3"},
		{">=8.2", "8.2"},
		{"~8.2", "8.2"},
		{"8.2.*", "8.2"},
		{"8.2.0", "8.2"},
		{"8", "8.0"},
	}

	for _, tt := range tests {
		t.Run(tt.constraint, func(t *T) {
			result := extractPHPVersion(tt.constraint)
			AssertEqual(t, tt.expected, result)
		})
	}
}

func TestPHP_DetectPHPExtensions_Good(t *T) {
	t.Run("detects Redis from predis", func(t *T) {
		composer := ComposerJSON{
			Require: map[string]string{
				"predis/predis": "^2.0",
			},
		}

		extensions := detectPHPExtensions(composer)
		AssertContains(t, extensions, "redis")
	})

	t.Run("detects GD from intervention/image", func(t *T) {
		composer := ComposerJSON{
			Require: map[string]string{
				"intervention/image": "^3.0",
			},
		}

		extensions := detectPHPExtensions(composer)
		AssertContains(t, extensions, "gd")
	})

	t.Run("detects multiple extensions from Laravel", func(t *T) {
		composer := ComposerJSON{
			Require: map[string]string{
				"laravel/framework": "^11.0",
			},
		}

		extensions := detectPHPExtensions(composer)
		AssertContains(t, extensions, "pdo_mysql")
		AssertContains(t, extensions, "bcmath")
	})

	t.Run("detects explicit ext- requirements", func(t *T) {
		composer := ComposerJSON{
			Require: map[string]string{
				"ext-gd":      "*",
				"ext-imagick": "*",
			},
		}

		extensions := detectPHPExtensions(composer)
		AssertContains(t, extensions, "gd")
		AssertContains(t, extensions, "imagick")
	})

	t.Run("skips built-in extensions", func(t *T) {
		composer := ComposerJSON{
			Require: map[string]string{
				"ext-json":    "*",
				"ext-session": "*",
				"ext-pdo":     "*",
			},
		}

		extensions := detectPHPExtensions(composer)
		AssertNotContains(t, extensions, "json")
		AssertNotContains(t, extensions, "session")
		AssertNotContains(t, extensions, "pdo")
	})

	t.Run("sorts extensions alphabetically", func(t *T) {
		composer := ComposerJSON{
			Require: map[string]string{
				"ext-zip":  "*",
				"ext-gd":   "*",
				"ext-intl": "*",
			},
		}

		extensions := detectPHPExtensions(composer)

		// Check they are sorted
		for i := 1; i < len(extensions); i++ {
			AssertTrue(t, extensions[i-1] < extensions[i], "extensions should be sorted")
		}
	})
}

func TestPHP_HasNodeAssets_Good(t *T) {
	t.Run("with build script", func(t *T) {
		dir := t.TempDir()

		packageJSON := `{
			"name": "test",
			"scripts": {
				"dev": "vite",
				"build": "vite build"
			}
		}`
		err := os.WriteFile(filepath.Join(dir, "package.json"), []byte(packageJSON), 0644)
		RequireNoError(t, err)

		AssertTrue(t, hasNodeAssets(dir))
	})
}

func TestPHP_HasNodeAssets_Bad(t *T) {
	t.Run("no package.json", func(t *T) {
		dir := t.TempDir()
		AssertFalse(t, hasNodeAssets(dir))
	})

	t.Run("no build script", func(t *T) {
		dir := t.TempDir()

		packageJSON := `{
			"name": "test",
			"scripts": {
				"dev": "vite"
			}
		}`
		err := os.WriteFile(filepath.Join(dir, "package.json"), []byte(packageJSON), 0644)
		RequireNoError(t, err)

		AssertFalse(t, hasNodeAssets(dir))
	})

	t.Run("invalid package.json", func(t *T) {
		dir := t.TempDir()

		err := os.WriteFile(filepath.Join(dir, "package.json"), []byte("invalid{"), 0644)
		RequireNoError(t, err)

		AssertFalse(t, hasNodeAssets(dir))
	})
}

func TestPHP_GenerateDockerignore_Good(t *T) {
	t.Run("generates complete dockerignore", func(t *T) {
		dir := t.TempDir()
		content := GenerateDockerignore(dir)

		// Check key entries
		AssertContains(t, content, ".git")
		AssertContains(t, content, "node_modules")
		AssertContains(t, content, ".env")
		AssertContains(t, content, "vendor")
		AssertContains(t, content, "storage/logs/*")
		AssertContains(t, content, ".idea")
		AssertContains(t, content, ".vscode")
	})
}

func TestPHP_GenerateDockerfileFromConfig_Good(t *T) {
	t.Run("minimal config", func(t *T) {
		config := &DockerfileConfig{
			PHPVersion: "8.3",
			BaseImage:  "dunglas/frankenphp",
			UseAlpine:  true,
		}

		content := GenerateDockerfileFromConfig(config)

		AssertContains(t, content, "FROM dunglas/frankenphp:latest-php8.3-alpine")
		AssertContains(t, content, "WORKDIR /app")
		AssertContains(t, content, "COPY composer.json composer.lock")
		AssertContains(t, content, "EXPOSE 80 443")
	})

	t.Run("with extensions", func(t *T) {
		config := &DockerfileConfig{
			PHPVersion:    "8.3",
			BaseImage:     "dunglas/frankenphp",
			UseAlpine:     true,
			PHPExtensions: []string{"redis", "gd", "intl"},
		}

		content := GenerateDockerfileFromConfig(config)

		AssertContains(t, content, "install-php-extensions redis gd intl")
	})

	t.Run("Laravel with Octane", func(t *T) {
		config := &DockerfileConfig{
			PHPVersion: "8.3",
			BaseImage:  "dunglas/frankenphp",
			UseAlpine:  true,
			IsLaravel:  true,
			HasOctane:  true,
		}

		content := GenerateDockerfileFromConfig(config)

		AssertContains(t, content, "php artisan config:cache")
		AssertContains(t, content, "php artisan route:cache")
		AssertContains(t, content, "php artisan view:cache")
		AssertContains(t, content, "chown -R www-data:www-data storage")
		AssertContains(t, content, "octane:start")
	})

	t.Run("with frontend assets", func(t *T) {
		config := &DockerfileConfig{
			PHPVersion:     "8.3",
			BaseImage:      "dunglas/frankenphp",
			UseAlpine:      true,
			HasAssets:      true,
			PackageManager: "npm",
		}

		content := GenerateDockerfileFromConfig(config)

		// Multi-stage build
		AssertContains(t, content, "FROM node:20-alpine AS frontend")
		AssertContains(t, content, "COPY package.json package-lock.json")
		AssertContains(t, content, "RUN npm ci")
		AssertContains(t, content, "RUN npm run build")
		AssertContains(t, content, "COPY --from=frontend /app/public/build public/build")
	})

	t.Run("with yarn", func(t *T) {
		config := &DockerfileConfig{
			PHPVersion:     "8.3",
			BaseImage:      "dunglas/frankenphp",
			UseAlpine:      true,
			HasAssets:      true,
			PackageManager: "yarn",
		}

		content := GenerateDockerfileFromConfig(config)

		AssertContains(t, content, "COPY package.json yarn.lock")
		AssertContains(t, content, "yarn install --frozen-lockfile")
		AssertContains(t, content, "yarn build")
	})

	t.Run("with bun", func(t *T) {
		config := &DockerfileConfig{
			PHPVersion:     "8.3",
			BaseImage:      "dunglas/frankenphp",
			UseAlpine:      true,
			HasAssets:      true,
			PackageManager: "bun",
		}

		content := GenerateDockerfileFromConfig(config)

		AssertContains(t, content, "npm install -g bun")
		AssertContains(t, content, "COPY package.json bun.lockb")
		AssertContains(t, content, "bun install --frozen-lockfile")
		AssertContains(t, content, "bun run build")
	})

	t.Run("non-alpine image", func(t *T) {
		config := &DockerfileConfig{
			PHPVersion: "8.3",
			BaseImage:  "dunglas/frankenphp",
			UseAlpine:  false,
		}

		content := GenerateDockerfileFromConfig(config)

		AssertContains(t, content, "FROM dunglas/frankenphp:latest-php8.3 AS app")
		AssertNotContains(t, content, "alpine")
	})
}

func TestPHP_IsPHPProject_Good(t *T) {
	t.Run("project with composer.json", func(t *T) {
		dir := t.TempDir()

		err := os.WriteFile(filepath.Join(dir, "composer.json"), []byte("{}"), 0644)
		RequireNoError(t, err)

		AssertTrue(t, IsPHPProject(dir))
	})
}

func TestPHP_IsPHPProject_Bad(t *T) {
	t.Run("project without composer.json", func(t *T) {
		dir := t.TempDir()
		AssertFalse(t, IsPHPProject(dir))
	})

	t.Run("non-existent directory", func(t *T) {
		AssertFalse(t, IsPHPProject("/non/existent/path"))
	})
}

func TestPHP_ExtractPHPVersion_Ugly(t *T) {
	t.Run("handles single major version", func(t *T) {
		result := extractPHPVersion("8")
		AssertEqual(t, "8.0", result)
	})
}

func TestDetectPHPExtensions_RequireDev(t *T) {
	t.Run("detects extensions from require-dev", func(t *T) {
		composer := ComposerJSON{
			RequireDev: map[string]string{
				"predis/predis": "^2.0",
			},
		}

		extensions := detectPHPExtensions(composer)
		AssertContains(t, extensions, "redis")
	})
}

func TestPHP_DockerfileStructure_Good(t *T) {
	t.Run("Dockerfile has proper structure", func(t *T) {
		dir := t.TempDir()

		composerJSON := `{
			"name": "test/app",
			"require": {
				"php": "^8.3",
				"laravel/framework": "^11.0",
				"laravel/octane": "^2.0",
				"predis/predis": "^2.0"
			}
		}`
		err := os.WriteFile(filepath.Join(dir, "composer.json"), []byte(composerJSON), 0644)
		RequireNoError(t, err)
		err = os.WriteFile(filepath.Join(dir, "composer.lock"), []byte("{}"), 0644)
		RequireNoError(t, err)

		packageJSON := `{"scripts": {"build": "vite build"}}`
		err = os.WriteFile(filepath.Join(dir, "package.json"), []byte(packageJSON), 0644)
		RequireNoError(t, err)
		err = os.WriteFile(filepath.Join(dir, "package-lock.json"), []byte("{}"), 0644)
		RequireNoError(t, err)

		content, err := GenerateDockerfile(dir)
		RequireNoError(t, err)

		lines := strings.Split(content, "\n")
		var fromCount, workdirCount, copyCount, runCount, exposeCount, cmdCount int

		for _, line := range lines {
			trimmed := strings.TrimSpace(line)
			switch {
			case strings.HasPrefix(trimmed, "FROM "):
				fromCount++
			case strings.HasPrefix(trimmed, "WORKDIR "):
				workdirCount++
			case strings.HasPrefix(trimmed, "COPY "):
				copyCount++
			case strings.HasPrefix(trimmed, "RUN "):
				runCount++
			case strings.HasPrefix(trimmed, "EXPOSE "):
				exposeCount++
			case strings.HasPrefix(trimmed, "CMD ["):
				// Only count actual CMD instructions, not HEALTHCHECK CMD
				cmdCount++
			}
		}

		// Multi-stage build should have 2 FROM statements
		AssertEqual(t, 2, fromCount, "should have 2 FROM statements for multi-stage build")

		// Should have proper structure
		AssertGreaterOrEqual(t, workdirCount, 1, "should have WORKDIR")
		AssertGreaterOrEqual(t, copyCount, 3, "should have multiple COPY statements")
		AssertGreaterOrEqual(t, runCount, 2, "should have multiple RUN statements")
		AssertEqual(t, 1, exposeCount, "should have exactly one EXPOSE")
		AssertEqual(t, 1, cmdCount, "should have exactly one CMD")
	})
}
