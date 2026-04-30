package php

import ()

func TestPHP_NewFrankenPHPService_Good(t *T) {
	t.Run(testDefaultOptions, func(t *T) {
		dir := testTmpDir
		service := NewFrankenPHPService(dir, FrankenPHPOptions{})

		AssertEqual(t, "FrankenPHP", service.Name())
		AssertEqual(t, 8000, service.port)
		AssertEqual(t, 443, service.httpsPort)
		AssertFalse(t, service.https)
	})

	t.Run("custom options", func(t *T) {
		dir := testTmpDir
		opts := FrankenPHPOptions{
			Port:      9000,
			HTTPSPort: 8443,
			HTTPS:     true,
			CertFile:  "cert.pem",
			KeyFile:   "key.pem",
		}
		service := NewFrankenPHPService(dir, opts)

		AssertEqual(t, 9000, service.port)
		AssertEqual(t, 8443, service.httpsPort)
		AssertTrue(t, service.https)
		AssertEqual(t, "cert.pem", service.certFile)
		AssertEqual(t, "key.pem", service.keyFile)
	})
}

func TestPHP_NewViteService_Good(t *T) {
	t.Run(testDefaultOptions, func(t *T) {
		dir := t.TempDir()
		service := NewViteService(dir, ViteOptions{})

		AssertEqual(t, "Vite", service.Name())
		AssertEqual(t, 5173, service.port)
		AssertEqual(t, "npm", service.packageManager) // default when no lock file
	})

	t.Run("custom package manager", func(t *T) {
		dir := t.TempDir()
		service := NewViteService(dir, ViteOptions{PackageManager: "pnpm"})

		AssertEqual(t, "pnpm", service.packageManager)
	})
}

func TestPHP_NewHorizonService_Good(t *T) {
	service := NewHorizonService(testTmpDir)
	AssertEqual(t, "Horizon", service.Name())
	AssertEqual(t, 0, service.port)
}

func TestPHP_NewReverbService_Good(t *T) {
	t.Run(testDefaultOptions, func(t *T) {
		service := NewReverbService(testTmpDir, ReverbOptions{})
		AssertEqual(t, "Reverb", service.Name())
		AssertEqual(t, 8080, service.port)
	})

	t.Run("custom port", func(t *T) {
		service := NewReverbService(testTmpDir, ReverbOptions{Port: 9090})
		AssertEqual(t, 9090, service.port)
	})
}

func TestPHP_NewRedisService_Good(t *T) {
	t.Run(testDefaultOptions, func(t *T) {
		service := NewRedisService(testTmpDir, RedisOptions{})
		AssertEqual(t, "Redis", service.Name())
		AssertEqual(t, 6379, service.port)
	})

	t.Run("custom config", func(t *T) {
		service := NewRedisService(testTmpDir, RedisOptions{ConfigFile: ax7RedisConfigFile})
		AssertEqual(t, ax7RedisConfigFile, service.configFile)
	})
}

func TestBaseService_Status(t *T) {
	s := &baseService{
		name:    "TestService",
		port:    1234,
		running: true,
	}

	status := s.Status()
	AssertEqual(t, "TestService", status.Name)
	AssertEqual(t, 1234, status.Port)
	AssertTrue(t, status.Running)
}
