package php

import (
	"os"
	"path/filepath"
)

func TestPHP_BaseService_Name_Good(t *T) {
	t.Run("returns service name", func(t *T) {
		s := &baseService{name: "TestService"}
		AssertEqual(t, "TestService", s.Name())
	})
}

func TestPHP_BaseService_Status_Good(t *T) {
	t.Run("returns status when not running", func(t *T) {
		s := &baseService{
			name:    "TestService",
			port:    8080,
			running: false,
		}

		status := s.Status()
		AssertEqual(t, "TestService", status.Name)
		AssertEqual(t, 8080, status.Port)
		AssertFalse(t, status.Running)
		AssertEqual(t, 0, status.PID)
	})

	t.Run("returns status when running", func(t *T) {
		s := &baseService{
			name:    "TestService",
			port:    8080,
			running: true,
		}

		status := s.Status()
		AssertTrue(t, status.Running)
	})

	t.Run("returns error in status", func(t *T) {
		testErr := AnError
		s := &baseService{
			name:      "TestService",
			lastError: testErr,
		}

		status := s.Status()
		AssertEqual(t, testErr, status.Error)
	})
}

func TestPHP_BaseService_Logs_Good(t *T) {
	t.Run("returns log file content", func(t *T) {
		dir := t.TempDir()
		logPath := filepath.Join(dir, "test.log")
		err := os.WriteFile(logPath, []byte("test log content"), 0644)
		RequireNoError(t, err)

		s := &baseService{logPath: logPath}
		reader, err := s.Logs(false)

		AssertNoError(t, err)
		AssertNotNil(t, reader)
		_ = reader.Close()
	})

	t.Run("returns tail reader in follow mode", func(t *T) {
		dir := t.TempDir()
		logPath := filepath.Join(dir, "test.log")
		err := os.WriteFile(logPath, []byte("test log content"), 0644)
		RequireNoError(t, err)

		s := &baseService{logPath: logPath}
		reader, err := s.Logs(true)

		AssertNoError(t, err)
		AssertNotNil(t, reader)
		// Verify it's a tailReader by checking it implements ReadCloser
		_, ok := reader.(*tailReader)
		AssertTrue(t, ok)
		_ = reader.Close()
	})
}

func TestPHP_BaseService_Logs_Bad(t *T) {
	t.Run("returns error when no log path", func(t *T) {
		s := &baseService{name: "TestService"}
		_, err := s.Logs(false)

		AssertError(t, err)
		AssertContains(t, err.Error(), "no log file available")
	})

	t.Run("returns error when log file doesn't exist", func(t *T) {
		s := &baseService{logPath: "/nonexistent/path/log.log"}
		_, err := s.Logs(false)
		AssertError(t, err)
		AssertContains(t, err.Error(), "failed to open log file")
	})
}

func TestPHP_TailReader_Good(t *T) {
	t.Run("creates new tail reader", func(t *T) {
		dir := t.TempDir()
		logPath := filepath.Join(dir, "test.log")
		err := os.WriteFile(logPath, []byte("content"), 0644)
		RequireNoError(t, err)

		file, err := os.Open(logPath)
		RequireNoError(t, err)
		defer func() { _ = file.Close() }()

		reader := newTailReader(file)
		AssertNotNil(t, reader)
		AssertNotNil(t, reader.file)
		AssertNotNil(t, reader.reader)
		AssertFalse(t, reader.closed)
	})

	t.Run("closes file on Close", func(t *T) {
		dir := t.TempDir()
		logPath := filepath.Join(dir, "test.log")
		err := os.WriteFile(logPath, []byte("content"), 0644)
		RequireNoError(t, err)

		file, err := os.Open(logPath)
		RequireNoError(t, err)

		reader := newTailReader(file)
		err = reader.Close()
		AssertNoError(t, err)
		AssertTrue(t, reader.closed)
	})

	t.Run("returns EOF when closed", func(t *T) {
		dir := t.TempDir()
		logPath := filepath.Join(dir, "test.log")
		err := os.WriteFile(logPath, []byte("content"), 0644)
		RequireNoError(t, err)

		file, err := os.Open(logPath)
		RequireNoError(t, err)

		reader := newTailReader(file)
		_ = reader.Close()

		buf := make([]byte, 100)
		n, _ := reader.Read(buf)
		// When closed, should return 0 bytes (the closed flag causes early return)
		AssertEqual(t, 0, n)
	})
}

func TestFrankenPHPService_Extended(t *T) {
	t.Run("all options set correctly", func(t *T) {
		opts := FrankenPHPOptions{
			Port:      9000,
			HTTPSPort: 9443,
			HTTPS:     true,
			CertFile:  "/path/to/cert.pem",
			KeyFile:   "/path/to/key.pem",
		}

		service := NewFrankenPHPService("/project", opts)

		AssertEqual(t, "FrankenPHP", service.Name())
		AssertEqual(t, 9000, service.port)
		AssertEqual(t, 9443, service.httpsPort)
		AssertTrue(t, service.https)
		AssertEqual(t, "/path/to/cert.pem", service.certFile)
		AssertEqual(t, "/path/to/key.pem", service.keyFile)
		AssertEqual(t, "/project", service.dir)
	})
}

func TestViteService_Extended(t *T) {
	t.Run("auto-detects package manager", func(t *T) {
		dir := t.TempDir()
		// Create bun.lockb to trigger bun detection
		err := os.WriteFile(filepath.Join(dir, "bun.lockb"), []byte(""), 0644)
		RequireNoError(t, err)

		service := NewViteService(dir, ViteOptions{})

		AssertEqual(t, "bun", service.packageManager)
	})

	t.Run("uses provided package manager", func(t *T) {
		dir := t.TempDir()

		service := NewViteService(dir, ViteOptions{PackageManager: "pnpm"})

		AssertEqual(t, "pnpm", service.packageManager)
	})
}

func TestHorizonService_Extended(t *T) {
	t.Run("has zero port", func(t *T) {
		service := NewHorizonService("/project")
		AssertEqual(t, 0, service.port)
	})
}

func TestReverbService_Extended(t *T) {
	t.Run("uses default port 8080", func(t *T) {
		service := NewReverbService("/project", ReverbOptions{})
		AssertEqual(t, 8080, service.port)
	})

	t.Run("uses custom port", func(t *T) {
		service := NewReverbService("/project", ReverbOptions{Port: 9090})
		AssertEqual(t, 9090, service.port)
	})
}

func TestRedisService_Extended(t *T) {
	t.Run("uses default port 6379", func(t *T) {
		service := NewRedisService("/project", RedisOptions{})
		AssertEqual(t, 6379, service.port)
	})

	t.Run("accepts config file", func(t *T) {
		service := NewRedisService("/project", RedisOptions{ConfigFile: "/path/to/redis.conf"})
		AssertEqual(t, "/path/to/redis.conf", service.configFile)
	})
}

func TestServiceStatus_Struct(t *T) {
	t.Run("all fields accessible", func(t *T) {
		testErr := AnError
		status := ServiceStatus{
			Name:    "TestService",
			Running: true,
			PID:     12345,
			Port:    8080,
			Error:   testErr,
		}

		AssertEqual(t, "TestService", status.Name)
		AssertTrue(t, status.Running)
		AssertEqual(t, 12345, status.PID)
		AssertEqual(t, 8080, status.Port)
		AssertEqual(t, testErr, status.Error)
	})
}

func TestFrankenPHPOptions_Struct(t *T) {
	t.Run("all fields accessible", func(t *T) {
		opts := FrankenPHPOptions{
			Port:      8000,
			HTTPSPort: 443,
			HTTPS:     true,
			CertFile:  "cert.pem",
			KeyFile:   "key.pem",
		}

		AssertEqual(t, 8000, opts.Port)
		AssertEqual(t, 443, opts.HTTPSPort)
		AssertTrue(t, opts.HTTPS)
		AssertEqual(t, "cert.pem", opts.CertFile)
		AssertEqual(t, "key.pem", opts.KeyFile)
	})
}

func TestViteOptions_Struct(t *T) {
	t.Run("all fields accessible", func(t *T) {
		opts := ViteOptions{
			Port:           5173,
			PackageManager: "bun",
		}

		AssertEqual(t, 5173, opts.Port)
		AssertEqual(t, "bun", opts.PackageManager)
	})
}

func TestReverbOptions_Struct(t *T) {
	t.Run("all fields accessible", func(t *T) {
		opts := ReverbOptions{Port: 8080}
		AssertEqual(t, 8080, opts.Port)
	})
}

func TestRedisOptions_Struct(t *T) {
	t.Run("all fields accessible", func(t *T) {
		opts := RedisOptions{
			Port:       6379,
			ConfigFile: "redis.conf",
		}

		AssertEqual(t, 6379, opts.Port)
		AssertEqual(t, "redis.conf", opts.ConfigFile)
	})
}

func TestPHP_BaseService_StopProcess_Good(t *T) {
	t.Run("returns nil when not running", func(t *T) {
		s := &baseService{running: false}
		err := s.stopProcess()
		AssertNoError(t, err)
	})

	t.Run("returns nil when cmd is nil", func(t *T) {
		s := &baseService{running: true, cmd: nil}
		err := s.stopProcess()
		AssertNoError(t, err)
	})
}
