//go:build frankenphp

package php

import (
	"context"
	"fmt"
	"log"
	"net/http"
	"os"
	"os/signal"
	"syscall"

	"dappco.re/go/cli/pkg/cli"
)

var (
	serveFPPort    int
	serveFPPath    string
	serveFPWorkers int
	serveFPThreads int
)

func init() {
	registerFrankenPHP = addFrankenPHPCommands
}

// addFrankenPHPCommands adds FrankenPHP-specific commands to the php parent command.
// Called from AddPHPCommands when CGO is enabled.
func addFrankenPHPCommands(phpCmd *cli.Command) {
	serveCmd := &cli.Command{
		Use:   "serve:embedded",
		Short: "Serve Laravel via embedded FrankenPHP runtime",
		Long:  "Start an HTTP server using the embedded FrankenPHP runtime with Octane worker mode support.",
		RunE:  runFrankenPHPServe,
	}
	serveCmd.Flags().IntVar(&serveFPPort, "port", 8000, "HTTP listen port")
	serveCmd.Flags().StringVar(&serveFPPath, "path", ".", "Laravel application root")
	serveCmd.Flags().IntVar(&serveFPWorkers, "workers", 2, "Octane worker count")
	serveCmd.Flags().IntVar(&serveFPThreads, "threads", 4, "PHP thread count")
	phpCmd.AddCommand(serveCmd)

	execCmd := &cli.Command{
		Use:   "exec [command...]",
		Short: "Execute a PHP artisan command via FrankenPHP",
		Long:  "Boot FrankenPHP, run an artisan command, then exit. Stdin/stdout pass-through.",
		Args:  cli.MinimumNArgs(1),
		RunE:  runFrankenPHPExec,
	}
	execCmd.Flags().StringVar(&serveFPPath, "path", ".", "Laravel application root")
	phpCmd.AddCommand(execCmd)
}

func runFrankenPHPServe(cmd *cli.Command, args []string) error {
	handler, cleanup, err := NewHandler(serveFPPath, HandlerConfig{
		NumThreads: serveFPThreads,
		NumWorkers: serveFPWorkers,
	})
	if err != nil {
		return fmt.Errorf("init FrankenPHP: %w", err)
	}
	defer cleanup()

	addr := fmt.Sprintf(":%d", serveFPPort)
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

func runFrankenPHPExec(cmd *cli.Command, args []string) error {
	handler, cleanup, err := NewHandler(serveFPPath, HandlerConfig{
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
