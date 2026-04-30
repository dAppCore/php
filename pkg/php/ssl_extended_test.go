package php

import (
	"os"
	"path/filepath"
)

func TestSSLOptions_Struct(t *T) {
	t.Run(testAllFieldsAccessible, func(t *T) {
		opts := SSLOptions{Dir: "/custom/ssl/dir"}
		AssertEqual(t, "/custom/ssl/dir", opts.Dir)
	})
}

func TestPHP_GetSSLDir_Bad(t *T) {
	t.Run("fails to create directory in invalid path", func(t *T) {
		// Try to create a directory in a path that can't exist
		opts := SSLOptions{Dir: testCannotCreatePath}
		_, err := GetSSLDir(opts)
		AssertError(t, err)
		AssertContains(t, err.Error(), "failed to create SSL directory")
	})
}

func TestPHP_CertPaths_Bad(t *T) {
	t.Run("fails when GetSSLDir fails", func(t *T) {
		opts := SSLOptions{Dir: testCannotCreatePath}
		_, _, err := CertPaths(testDomain, opts)
		AssertError(t, err)
	})
}

func TestCertsExist_Detailed(t *T) {
	t.Run("returns true when both cert and key exist", func(t *T) {
		dir := t.TempDir()
		domain := testLocalDomain

		// Create both files
		certPath := filepath.Join(dir, domain+".pem")
		keyPath := filepath.Join(dir, domain+testKeySuffix)

		err := os.WriteFile(certPath, []byte("cert"), 0644)
		RequireNoError(t, err)
		err = os.WriteFile(keyPath, []byte("key"), 0644)
		RequireNoError(t, err)

		result := CertsExist(domain, SSLOptions{Dir: dir})
		AssertTrue(t, result)
	})

	t.Run("returns false when only cert exists", func(t *T) {
		dir := t.TempDir()
		domain := testLocalDomain

		certPath := filepath.Join(dir, domain+".pem")
		err := os.WriteFile(certPath, []byte("cert"), 0644)
		RequireNoError(t, err)

		result := CertsExist(domain, SSLOptions{Dir: dir})
		AssertFalse(t, result)
	})

	t.Run("returns false when only key exists", func(t *T) {
		dir := t.TempDir()
		domain := testLocalDomain

		keyPath := filepath.Join(dir, domain+testKeySuffix)
		err := os.WriteFile(keyPath, []byte("key"), 0644)
		RequireNoError(t, err)

		result := CertsExist(domain, SSLOptions{Dir: dir})
		AssertFalse(t, result)
	})

	t.Run("returns false when CertPaths fails", func(t *T) {
		result := CertsExist(testDomain, SSLOptions{Dir: testCannotCreatePath})
		AssertFalse(t, result)
	})
}

func TestSetupSSL_RequiresMkcert(t *T) {
	t.Run(testMkcertMissingName, func(t *T) {
		if IsMkcertInstalled() {
			t.Skip(testMkcertInstalledSkip)
		}

		err := SetupSSL("example.test", SSLOptions{})
		AssertError(t, err)
		AssertContains(t, err.Error(), testMkcertNotInstalled)
	})
}

func TestSetupSSLIfNeeded_UsesExisting(t *T) {
	t.Run("returns existing certs without regenerating", func(t *T) {
		dir := t.TempDir()
		domain := "existing.test"

		// Create existing certs
		certPath := filepath.Join(dir, domain+".pem")
		keyPath := filepath.Join(dir, domain+testKeySuffix)

		err := os.WriteFile(certPath, []byte("existing cert"), 0644)
		RequireNoError(t, err)
		err = os.WriteFile(keyPath, []byte("existing key"), 0644)
		RequireNoError(t, err)

		resultCert, resultKey, err := SetupSSLIfNeeded(domain, SSLOptions{Dir: dir})

		AssertNoError(t, err)
		AssertEqual(t, certPath, resultCert)
		AssertEqual(t, keyPath, resultKey)

		// Verify original content wasn't changed
		content, _ := os.ReadFile(certPath)
		AssertEqual(t, "existing cert", string(content))
	})
}

func TestPHP_SetupSSLIfNeeded_Bad(t *T) {
	t.Run("fails when CertPaths fails", func(t *T) {
		_, _, err := SetupSSLIfNeeded(testDomain, SSLOptions{Dir: testCannotCreatePath})
		AssertError(t, err)
	})

	t.Run("fails when SetupSSL fails", func(t *T) {
		if IsMkcertInstalled() {
			t.Skip(testMkcertInstalledSkip)
		}

		dir := t.TempDir()
		_, _, err := SetupSSLIfNeeded(testDomain, SSLOptions{Dir: dir})
		AssertError(t, err)
	})
}

func TestPHP_InstallMkcertCA_Bad(t *T) {
	t.Run(testMkcertMissingName, func(t *T) {
		if IsMkcertInstalled() {
			t.Skip(testMkcertInstalledSkip)
		}

		err := InstallMkcertCA()
		AssertError(t, err)
		AssertContains(t, err.Error(), testMkcertNotInstalled)
	})
}

func TestPHP_GetMkcertCARoot_Bad(t *T) {
	t.Run(testMkcertMissingName, func(t *T) {
		if IsMkcertInstalled() {
			t.Skip(testMkcertInstalledSkip)
		}

		_, err := GetMkcertCARoot()
		AssertError(t, err)
		AssertContains(t, err.Error(), testMkcertNotInstalled)
	})
}

func TestCertPathsNaming(t *T) {
	t.Run("uses correct naming convention", func(t *T) {
		dir := t.TempDir()
		domain := "myapp.example.com"

		certFile, keyFile, err := CertPaths(domain, SSLOptions{Dir: dir})

		AssertNoError(t, err)
		AssertEqual(t, filepath.Join(dir, "myapp.example.com.pem"), certFile)
		AssertEqual(t, filepath.Join(dir, "myapp.example.com-key.pem"), keyFile)
	})

	t.Run("handles localhost", func(t *T) {
		dir := t.TempDir()

		certFile, keyFile, err := CertPaths("localhost", SSLOptions{Dir: dir})

		AssertNoError(t, err)
		AssertEqual(t, filepath.Join(dir, "localhost.pem"), certFile)
		AssertEqual(t, filepath.Join(dir, "localhost-key.pem"), keyFile)
	})

	t.Run("handles wildcard-like domains", func(t *T) {
		dir := t.TempDir()
		domain := "*.example.com"

		certFile, keyFile, err := CertPaths(domain, SSLOptions{Dir: dir})

		AssertNoError(t, err)
		AssertContains(t, certFile, "*.example.com.pem")
		AssertContains(t, keyFile, "*.example.com-key.pem")
	})
}

func TestDefaultSSLDir_Value(t *T) {
	t.Run("has expected default value", func(t *T) {
		AssertEqual(t, ".core/ssl", DefaultSSLDir)
	})
}

func TestGetSSLDir_CreatesDirectory(t *T) {
	t.Run("creates nested directory structure", func(t *T) {
		baseDir := t.TempDir()
		nestedDir := filepath.Join(baseDir, "level1", "level2", "ssl")

		dir, err := GetSSLDir(SSLOptions{Dir: nestedDir})

		AssertNoError(t, err)
		AssertEqual(t, nestedDir, dir)

		// Verify directory exists
		info, err := os.Stat(dir)
		AssertNoError(t, err)
		AssertTrue(t, info.IsDir())
	})
}
