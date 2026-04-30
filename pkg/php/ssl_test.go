package php

import (
	"os"
	"path/filepath"
)

func TestPHP_GetSSLDir_Good(t *T) {
	t.Run("uses provided directory", func(t *T) {
		dir := t.TempDir()
		customDir := filepath.Join(dir, "custom-ssl")

		result, err := GetSSLDir(SSLOptions{Dir: customDir})

		AssertNoError(t, err)
		AssertEqual(t, customDir, result)

		// Verify directory was created
		info, err := os.Stat(result)
		AssertNoError(t, err)
		AssertTrue(t, info.IsDir())
	})

	t.Run("uses default directory when not specified", func(t *T) {
		// Skip if we can't get home dir
		home, err := os.UserHomeDir()
		if err != nil {
			t.Skip("cannot get home directory")
		}

		result, err := GetSSLDir(SSLOptions{})

		AssertNoError(t, err)
		AssertEqual(t, filepath.Join(home, DefaultSSLDir), result)
	})
}

func TestPHP_CertPaths_Good(t *T) {
	t.Run("returns correct paths for domain", func(t *T) {
		dir := t.TempDir()

		certFile, keyFile, err := CertPaths("example.test", SSLOptions{Dir: dir})

		AssertNoError(t, err)
		AssertEqual(t, filepath.Join(dir, "example.test.pem"), certFile)
		AssertEqual(t, filepath.Join(dir, "example.test-key.pem"), keyFile)
	})

	t.Run("handles domain with subdomain", func(t *T) {
		dir := t.TempDir()

		certFile, keyFile, err := CertPaths("app.example.test", SSLOptions{Dir: dir})

		AssertNoError(t, err)
		AssertEqual(t, filepath.Join(dir, "app.example.test.pem"), certFile)
		AssertEqual(t, filepath.Join(dir, "app.example.test-key.pem"), keyFile)
	})
}

func TestPHP_CertsExist_Good(t *T) {
	t.Run("returns true when both files exist", func(t *T) {
		dir := t.TempDir()
		domain := testMyAppDomain

		// Create cert and key files
		certFile := filepath.Join(dir, domain+".pem")
		keyFile := filepath.Join(dir, domain+testKeySuffix)

		err := os.WriteFile(certFile, []byte("cert content"), 0644)
		RequireNoError(t, err)
		err = os.WriteFile(keyFile, []byte("key content"), 0644)
		RequireNoError(t, err)

		AssertTrue(t, CertsExist(domain, SSLOptions{Dir: dir}))
	})
}

func TestPHP_CertsExist_Bad(t *T) {
	t.Run("returns false when cert missing", func(t *T) {
		dir := t.TempDir()
		domain := testMyAppDomain

		// Create only key file
		keyFile := filepath.Join(dir, domain+testKeySuffix)
		err := os.WriteFile(keyFile, []byte("key content"), 0644)
		RequireNoError(t, err)

		AssertFalse(t, CertsExist(domain, SSLOptions{Dir: dir}))
	})

	t.Run("returns false when key missing", func(t *T) {
		dir := t.TempDir()
		domain := testMyAppDomain

		// Create only cert file
		certFile := filepath.Join(dir, domain+".pem")
		err := os.WriteFile(certFile, []byte("cert content"), 0644)
		RequireNoError(t, err)

		AssertFalse(t, CertsExist(domain, SSLOptions{Dir: dir}))
	})

	t.Run("returns false when neither exists", func(t *T) {
		dir := t.TempDir()
		domain := testMyAppDomain

		AssertFalse(t, CertsExist(domain, SSLOptions{Dir: dir}))
	})

	t.Run("returns false for invalid directory", func(t *T) {
		// Use invalid directory path
		AssertFalse(t, CertsExist(testDomain, SSLOptions{Dir: "/nonexistent/path/that/does/not/exist"}))
	})
}

func TestPHP_SetupSSL_Bad(t *T) {
	t.Run("returns error when mkcert not installed", func(t *T) {
		// This test assumes mkcert might not be installed
		// If it is installed, we skip this test
		if IsMkcertInstalled() {
			t.Skip(testMkcertInstalledSkip)
		}

		err := SetupSSL("example.test", SSLOptions{})
		AssertError(t, err)
		AssertContains(t, err.Error(), testMkcertNotInstalled)
	})
}

func TestPHP_SetupSSLIfNeeded_Good(t *T) {
	t.Run("returns existing certs without regenerating", func(t *T) {
		dir := t.TempDir()
		domain := "existing.test"

		// Create existing cert files
		certFile := filepath.Join(dir, domain+".pem")
		keyFile := filepath.Join(dir, domain+testKeySuffix)

		err := os.WriteFile(certFile, []byte("existing cert"), 0644)
		RequireNoError(t, err)
		err = os.WriteFile(keyFile, []byte("existing key"), 0644)
		RequireNoError(t, err)

		resultCert, resultKey, err := SetupSSLIfNeeded(domain, SSLOptions{Dir: dir})

		AssertNoError(t, err)
		AssertEqual(t, certFile, resultCert)
		AssertEqual(t, keyFile, resultKey)

		// Verify files weren't modified
		data, err := os.ReadFile(certFile)
		RequireNoError(t, err)
		AssertEqual(t, "existing cert", string(data))
	})
}

func TestPHP_IsMkcertInstalled_Good(t *T) {
	// This test just verifies the function runs without error
	// The actual result depends on whether mkcert is installed
	result := IsMkcertInstalled()
	again := IsMkcertInstalled()
	AssertEqual(t, result, again)
	t.Logf("mkcert installed: %v", result)
}

func TestPHP_DefaultSSLDir_Good(t *T) {
	t.Run("constant has expected value", func(t *T) {
		AssertEqual(t, ".core/ssl", DefaultSSLDir)
	})
}
