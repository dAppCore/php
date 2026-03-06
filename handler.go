//go:build cgo

// Package php provides FrankenPHP embedding for Go applications.
// Serves a Laravel application via the FrankenPHP runtime, with support
// for Octane worker mode (in-memory, sub-ms responses) and standard mode
// fallback. Designed for use with Wails v3's AssetOptions.Handler.
package php

import (
	"fmt"
	"log"
	"net/http"
	"os"
	"path/filepath"
	"strings"

	"github.com/dunglas/frankenphp"
)

// Handler implements http.Handler by delegating to FrankenPHP.
// It resolves URLs to files (Caddy try_files pattern) before passing
// requests to the PHP runtime.
type Handler struct {
	docRoot     string
	laravelRoot string
}

// HandlerConfig configures the FrankenPHP handler.
type HandlerConfig struct {
	// NumThreads is the number of PHP threads (default: 4).
	NumThreads int
	// NumWorkers is the number of Octane workers (default: 2).
	NumWorkers int
	// PHPIni provides php.ini overrides.
	PHPIni map[string]string
}

// NewHandler extracts the Laravel app from the given filesystem, prepares the
// environment, initialises FrankenPHP with worker mode, and returns the handler.
// The cleanup function must be called on shutdown to release resources and remove
// the extracted files.
func NewHandler(laravelRoot string, cfg HandlerConfig) (*Handler, func(), error) {
	if cfg.NumThreads == 0 {
		cfg.NumThreads = 4
	}
	if cfg.NumWorkers == 0 {
		cfg.NumWorkers = 2
	}
	if cfg.PHPIni == nil {
		cfg.PHPIni = map[string]string{
			"display_errors": "Off",
			"opcache.enable": "1",
		}
	}

	docRoot := filepath.Join(laravelRoot, "public")

	log.Printf("go-php: Laravel root: %s", laravelRoot)
	log.Printf("go-php: Document root: %s", docRoot)

	// Try Octane worker mode first, fall back to standard mode.
	// Worker mode keeps Laravel booted in memory — sub-ms response times.
	workerScript := filepath.Join(laravelRoot, "vendor", "laravel", "octane", "bin", "frankenphp-worker.php")
	workerEnv := map[string]string{
		"APP_BASE_PATH":     laravelRoot,
		"FRANKENPHP_WORKER": "1",
	}

	workerMode := false
	if _, err := os.Stat(workerScript); err == nil {
		if err := frankenphp.Init(
			frankenphp.WithNumThreads(cfg.NumThreads),
			frankenphp.WithWorkers("laravel", workerScript, cfg.NumWorkers, workerEnv, nil),
			frankenphp.WithPhpIni(cfg.PHPIni),
		); err != nil {
			log.Printf("go-php: worker mode init failed (%v), falling back to standard mode", err)
		} else {
			workerMode = true
		}
	}

	if !workerMode {
		if err := frankenphp.Init(
			frankenphp.WithNumThreads(cfg.NumThreads),
			frankenphp.WithPhpIni(cfg.PHPIni),
		); err != nil {
			return nil, nil, fmt.Errorf("init FrankenPHP: %w", err)
		}
	}

	if workerMode {
		log.Printf("go-php: FrankenPHP initialised (Octane worker mode, %d workers)", cfg.NumWorkers)
	} else {
		log.Printf("go-php: FrankenPHP initialised (standard mode, %d threads)", cfg.NumThreads)
	}

	cleanup := func() {
		frankenphp.Shutdown()
	}

	handler := &Handler{
		docRoot:     docRoot,
		laravelRoot: laravelRoot,
	}

	return handler, cleanup, nil
}

// LaravelRoot returns the path to the extracted Laravel application.
func (h *Handler) LaravelRoot() string {
	return h.laravelRoot
}

// DocRoot returns the path to the document root (public/).
func (h *Handler) DocRoot() string {
	return h.docRoot
}

func (h *Handler) ServeHTTP(w http.ResponseWriter, r *http.Request) {
	urlPath := r.URL.Path
	filePath := filepath.Join(h.docRoot, filepath.Clean(urlPath))

	info, err := os.Stat(filePath)
	if err == nil && info.IsDir() {
		// Directory → try index.php inside it
		urlPath = strings.TrimRight(urlPath, "/") + "/index.php"
	} else if err != nil && !strings.HasSuffix(urlPath, ".php") {
		// File not found and not a .php request → front controller
		urlPath = "/index.php"
	}

	// Serve static assets directly (CSS, JS, images)
	if !strings.HasSuffix(urlPath, ".php") {
		staticPath := filepath.Join(h.docRoot, filepath.Clean(urlPath))
		if info, err := os.Stat(staticPath); err == nil && !info.IsDir() {
			http.ServeFile(w, r, staticPath)
			return
		}
	}

	// Route to FrankenPHP
	r.URL.Path = urlPath

	req, err := frankenphp.NewRequestWithContext(r,
		frankenphp.WithRequestDocumentRoot(h.docRoot, false),
	)
	if err != nil {
		http.Error(w, fmt.Sprintf("FrankenPHP request error: %v", err), http.StatusInternalServerError)
		return
	}

	if err := frankenphp.ServeHTTP(w, req); err != nil {
		http.Error(w, fmt.Sprintf("FrankenPHP serve error: %v", err), http.StatusInternalServerError)
	}
}
