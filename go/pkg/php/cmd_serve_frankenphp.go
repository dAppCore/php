//go:build frankenphp

package php

import (
	"context"
	`fmt`
	`log`
	"net/http"
	`os`
	"os/signal"
	"syscall"

	core "dappco.re/go"
)

func init() {
	registerFrankenPHP = addFrankenPHPCommands
}

// addFrankenPHPCommands adds FrankenPHP-specific commands to the php parent command.
// Called from AddPHPCommands when CGO is enabled.
func addFrankenPHPCommands(c *core.Core, prefix string) {
	servePath := phpCommandPath(prefix, "serve:embedded")
	phpFailureorCommand(c, servePath, "Serve Laravel via embedded FrankenPHP runtime", func(opts core.Options) error {
		return runFrankenPHPServe(phpCommandLineFor(servePath, opts))
	})

	execPath := phpCommandPath(prefix, "exec")
	phpFailureorCommand(c, execPath, "Execute a PHP artisan command via FrankenPHP", func(opts core.Options) error {
		return runFrankenPHPExec(phpCommandLineFor(execPath, opts))
	})
}

func runFrankenPHPServe(line phpCommandLine) error { // Result boundary
	handler, cleanup, err := NewHandler(line.String(`path`, "."), HandlerConfig{
		NumThreads: line.Int("threads", 4),
		NumWorkers: line.Int("workers", 2),
	})
	if err != nil {
		return fmt.Errorf("init FrankenPHP: %w", err)
	}
	defer cleanup()

	addr := fmt.Sprintf(":%d", line.Int("port", 8000))
	srv := &http.Server{
		Addr:    addr,
		Handler: handler,
	}

	// Graceful shutdown
	ctx, stop := signal.NotifyContext(context.Background(), os.Interrupt, syscall.SIGTERM)
	defer stop()

	go func() {
		log.Printf("core-php: serving on http://localhost%s (doc root: %s)", addr, handler.DocRoot())
		if err := srv.ListenAndServe(); err != nil && err != http.ErrServerClosed {
			log.Fatalf("core-php: server error: %v", err)
		}
	}()

	<-ctx.Done()
	log.Println("core-php: shutting down...")
	return srv.Shutdown(context.Background())
}

func runFrankenPHPExec(line phpCommandLine) error { // Result boundary
	args := line.Args()
	if len(args) < 1 {
		return phpFailure("requires at least 1 arg(s), only received %d", len(args))
	}

	handler, cleanup, err := NewHandler(line.String(`path`, "."), HandlerConfig{
		NumThreads: 1,
		NumWorkers: 0,
	})
	if err != nil {
		return fmt.Errorf("init FrankenPHP: %w", err)
	}
	defer cleanup()

	// Build an artisan request
	artisanArgs := "artisan"
	for _, a := range args {
		artisanArgs += " " + a
	}

	log.Printf("core-php: exec %s (root: %s)", artisanArgs, handler.LaravelRoot())

	// Execute via internal HTTP request to FrankenPHP
	// This routes through the PHP runtime as if it were a CLI call
	req, err := http.NewRequest("GET", "/artisan-exec?cmd="+artisanArgs, nil)
	if err != nil {
		return err
	}

	// For now, use the handler directly
	w := &execResponseWriter{os.Stdout}
	handler.ServeHTTP(w, req)

	return nil
}

// execResponseWriter writes HTTP response body directly to stdout.
type execResponseWriter struct {
	out *os.File
}

func (w *execResponseWriter) Header() http.Header         { return http.Header{} }
func (w *execResponseWriter) Write(b []byte) (int, error) { return w.out.Write(b) }
func (w *execResponseWriter) WriteHeader(_ int) {
	// Status headers are ignored because this bridge streams only the body.
}
