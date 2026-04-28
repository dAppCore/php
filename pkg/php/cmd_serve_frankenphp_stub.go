//go:build !frankenphp

package php

import (
	"net/http"
	"os"
)

// execResponseWriter writes HTTP response body directly to stdout.
type execResponseWriter struct {
	out *os.File
}

func (w *execResponseWriter) Header() http.Header         { return http.Header{} }
func (w *execResponseWriter) WriteHeader(_ int)           {}
func (w *execResponseWriter) Write(b []byte) (int, error) { return w.out.Write(b) }
