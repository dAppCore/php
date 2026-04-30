package php

import (
	"os"
	"path/filepath"
)

func TestPHP_LoadCoolifyConfig_Good(t *T) {
	tests := []struct {
		name        string
		envContent  string
		wantURL     string
		wantToken   string
		wantAppID   string
		wantStaging string
	}{
		{
			name: "all values set",
			envContent: `COOLIFY_URL=https://coolify.example.com
COOLIFY_TOKEN=secret-token
COOLIFY_APP_ID=app-123
COOLIFY_STAGING_APP_ID=staging-456`,
			wantURL:     testCoolifyURL,
			wantToken:   testCoolifyToken,
			wantAppID:   testCoolifyAppID,
			wantStaging: testCoolifyStagingAppID,
		},
		{
			name: "quoted values",
			envContent: "COOLIFY_URL=\"" + testCoolifyURL + "\"\n" +
				"COOLIFY_TOKEN='" + testCoolifyToken + "'\n" +
				"COOLIFY_APP_ID=\"" + testCoolifyAppID + "\"",
			wantURL:   testCoolifyURL,
			wantToken: testCoolifyToken,
			wantAppID: testCoolifyAppID,
		},
		{
			name: "with comments and blank lines",
			envContent: `# Coolify configuration
COOLIFY_URL=https://coolify.example.com

# API token
COOLIFY_TOKEN=secret-token
COOLIFY_APP_ID=app-123
# COOLIFY_STAGING_APP_ID=not-this`,
			wantURL:   testCoolifyURL,
			wantToken: testCoolifyToken,
			wantAppID: testCoolifyAppID,
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *T) {
			dir := t.TempDir()
			writeCoolifyEnv(t, dir, tt.envContent)
			config := loadCoolifyConfigForTest(t, dir)
			assertCoolifyConfig(t, config, tt.wantURL, tt.wantToken, tt.wantAppID, tt.wantStaging)
		})
	}
}

func writeCoolifyEnv(t *T, dir, content string) {
	t.Helper()
	envPath := filepath.Join(dir, ".env")
	if err := os.WriteFile(envPath, []byte(content), 0644); err != nil {
		t.Fatalf("failed to write .env: %v", err)
	}
}

func loadCoolifyConfigForTest(t *T, dir string) *CoolifyConfig {
	t.Helper()
	config, err := LoadCoolifyConfig(dir)
	if err != nil {
		t.Fatalf("LoadCoolifyConfig() error = %v", err)
	}
	return config
}

func assertCoolifyConfig(t *T, config *CoolifyConfig, wantURL, wantToken, wantAppID, wantStaging string) {
	t.Helper()
	if config.URL != wantURL {
		t.Errorf("URL = %q, want %q", config.URL, wantURL)
	}
	if config.Token != wantToken {
		t.Errorf("Token = %q, want %q", config.Token, wantToken)
	}
	if config.AppID != wantAppID {
		t.Errorf("AppID = %q, want %q", config.AppID, wantAppID)
	}
	if wantStaging != "" && config.StagingAppID != wantStaging {
		t.Errorf("StagingAppID = %q, want %q", config.StagingAppID, wantStaging)
	}
}

func TestPHP_LoadCoolifyConfig_Bad(t *T) {
	tests := []struct {
		name       string
		envContent string
		wantErr    string
	}{
		{
			name:       "missing URL",
			envContent: "COOLIFY_TOKEN=secret",
			wantErr:    "COOLIFY_URL is not set",
		},
		{
			name:       "missing token",
			envContent: "COOLIFY_URL=https://coolify.example.com",
			wantErr:    "COOLIFY_TOKEN is not set",
		},
		{
			name:       "empty values",
			envContent: "COOLIFY_URL=\nCOOLIFY_TOKEN=",
			wantErr:    "COOLIFY_URL is not set",
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *T) {
			// Create temp directory
			dir := t.TempDir()
			envPath := filepath.Join(dir, ".env")

			// Write .env file
			if err := os.WriteFile(envPath, []byte(tt.envContent), 0644); err != nil {
				t.Fatalf("failed to write .env: %v", err)
			}

			// Load config
			_, err := LoadCoolifyConfig(dir)
			if err == nil {
				t.Fatal("LoadCoolifyConfig() expected error, got nil")
			}

			if err.Error() != tt.wantErr {
				t.Errorf("error = %q, want %q", err.Error(), tt.wantErr)
			}
		})
	}
}

func TestPHP_GetAppIDForEnvironment_Good(t *T) {
	config := &CoolifyConfig{
		URL:          testCoolifyURL,
		Token:        "token",
		AppID:        testProdAppID,
		StagingAppID: testCoolifyStagingAppID,
	}

	tests := []struct {
		name   string
		env    Environment
		wantID string
	}{
		{
			name:   "production environment",
			env:    EnvProduction,
			wantID: testProdAppID,
		},
		{
			name:   "staging environment",
			env:    EnvStaging,
			wantID: testCoolifyStagingAppID,
		},
		{
			name:   "empty defaults to production",
			env:    "",
			wantID: testProdAppID,
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *T) {
			id := getAppIDForEnvironment(config, tt.env)
			if id != tt.wantID {
				t.Errorf("getAppIDForEnvironment() = %q, want %q", id, tt.wantID)
			}
		})
	}
}

func TestGetAppIDForEnvironment_FallbackToProduction(t *T) {
	config := &CoolifyConfig{
		URL:   testCoolifyURL,
		Token: "token",
		AppID: testProdAppID,
		// No staging app ID
	}

	// Staging should fall back to production
	id := getAppIDForEnvironment(config, EnvStaging)
	if id != testProdAppID {
		t.Errorf("getAppIDForEnvironment(EnvStaging) = %q, want %q (should fallback)", id, testProdAppID)
	}
}

func TestPHP_IsDeploymentComplete_Good(t *T) {
	completeStatuses := []string{"finished", "success", "failed", "error", "cancelled"}
	for _, status := range completeStatuses {
		if !IsDeploymentComplete(status) {
			t.Errorf("IsDeploymentComplete(%q) = false, want true", status)
		}
	}

	incompleteStatuses := []string{"queued", "building", "deploying", "pending", "rolling_back"}
	for _, status := range incompleteStatuses {
		if IsDeploymentComplete(status) {
			t.Errorf("IsDeploymentComplete(%q) = true, want false", status)
		}
	}
}

func TestPHP_IsDeploymentSuccessful_Good(t *T) {
	successStatuses := []string{"finished", "success"}
	for _, status := range successStatuses {
		if !IsDeploymentSuccessful(status) {
			t.Errorf("IsDeploymentSuccessful(%q) = false, want true", status)
		}
	}

	failedStatuses := []string{"failed", "error", "cancelled", "queued", "building"}
	for _, status := range failedStatuses {
		if IsDeploymentSuccessful(status) {
			t.Errorf("IsDeploymentSuccessful(%q) = true, want false", status)
		}
	}
}

func TestPHP_NewCoolifyClient_Good(t *T) {
	tests := []struct {
		name        string
		baseURL     string
		wantBaseURL string
	}{
		{
			name:        "URL without trailing slash",
			baseURL:     testCoolifyURL,
			wantBaseURL: testCoolifyURL,
		},
		{
			name:        "URL with trailing slash",
			baseURL:     "https://coolify.example.com/",
			wantBaseURL: testCoolifyURL,
		},
		{
			name:        "URL with api path",
			baseURL:     "https://coolify.example.com/api/",
			wantBaseURL: "https://coolify.example.com/api",
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *T) {
			client := NewCoolifyClient(tt.baseURL, "token")
			if client.BaseURL != tt.wantBaseURL {
				t.Errorf("BaseURL = %q, want %q", client.BaseURL, tt.wantBaseURL)
			}
			if client.Token != "token" {
				t.Errorf("Token = %q, want %q", client.Token, "token")
			}
			if client.HTTPClient == nil {
				t.Error("HTTPClient is nil")
			}
		})
	}
}
