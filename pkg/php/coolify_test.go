package php

import (
	"context"
	"encoding/json"
	"net/http"
	"net/http/httptest"
	"os"
	"path/filepath"
	"time"
)

func TestPHP_CoolifyClient_Good(t *T) {
	t.Run("creates client with correct base URL", func(t *T) {
		client := NewCoolifyClient("https://coolify.example.com", "token")

		AssertEqual(t, "https://coolify.example.com", client.BaseURL)
		AssertEqual(t, "token", client.Token)
		AssertNotNil(t, client.HTTPClient)
	})

	t.Run("strips trailing slash from base URL", func(t *T) {
		client := NewCoolifyClient("https://coolify.example.com/", "token")
		AssertEqual(t, "https://coolify.example.com", client.BaseURL)
	})

	t.Run("http client has timeout", func(t *T) {
		client := NewCoolifyClient("https://coolify.example.com", "token")
		AssertEqual(t, 30*time.Second, client.HTTPClient.Timeout)
	})
}

func TestPHP_CoolifyConfig_Good(t *T) {
	t.Run("all fields accessible", func(t *T) {
		config := CoolifyConfig{
			URL:          "https://coolify.example.com",
			Token:        "secret-token",
			AppID:        "app-123",
			StagingAppID: "staging-456",
		}

		AssertEqual(t, "https://coolify.example.com", config.URL)
		AssertEqual(t, "secret-token", config.Token)
		AssertEqual(t, "app-123", config.AppID)
		AssertEqual(t, "staging-456", config.StagingAppID)
	})
}

func TestPHP_CoolifyDeployment_Good(t *T) {
	t.Run("all fields accessible", func(t *T) {
		now := time.Now()
		deployment := CoolifyDeployment{
			ID:          "dep-123",
			Status:      "finished",
			CommitSHA:   "abc123",
			CommitMsg:   "Test commit",
			Branch:      "main",
			CreatedAt:   now,
			FinishedAt:  now.Add(5 * time.Minute),
			Log:         "Build successful",
			DeployedURL: "https://app.example.com",
		}

		AssertEqual(t, "dep-123", deployment.ID)
		AssertEqual(t, "finished", deployment.Status)
		AssertEqual(t, "abc123", deployment.CommitSHA)
		AssertEqual(t, "Test commit", deployment.CommitMsg)
		AssertEqual(t, "main", deployment.Branch)
	})
}

func TestPHP_CoolifyApp_Good(t *T) {
	t.Run("all fields accessible", func(t *T) {
		app := CoolifyApp{
			ID:          "app-123",
			Name:        "MyApp",
			FQDN:        "https://myapp.example.com",
			Status:      "running",
			Repository:  "https://github.com/user/repo",
			Branch:      "main",
			Environment: "production",
		}

		AssertEqual(t, "app-123", app.ID)
		AssertEqual(t, "MyApp", app.Name)
		AssertEqual(t, "https://myapp.example.com", app.FQDN)
		AssertEqual(t, "running", app.Status)
	})
}

func TestPHP_LoadCoolifyConfigFromFile_Good(t *T) {
	t.Run("loads config from .env file", func(t *T) {
		dir := t.TempDir()
		envContent := `COOLIFY_URL=https://coolify.example.com
COOLIFY_TOKEN=secret-token
COOLIFY_APP_ID=app-123
COOLIFY_STAGING_APP_ID=staging-456`

		err := os.WriteFile(filepath.Join(dir, ".env"), []byte(envContent), 0644)
		RequireNoError(t, err)

		config, err := LoadCoolifyConfigFromFile(filepath.Join(dir, ".env"))
		AssertNoError(t, err)
		AssertEqual(t, "https://coolify.example.com", config.URL)
		AssertEqual(t, "secret-token", config.Token)
		AssertEqual(t, "app-123", config.AppID)
		AssertEqual(t, "staging-456", config.StagingAppID)
	})

	t.Run("handles quoted values", func(t *T) {
		dir := t.TempDir()
		envContent := `COOLIFY_URL="https://coolify.example.com"
COOLIFY_TOKEN='secret-token'`

		err := os.WriteFile(filepath.Join(dir, ".env"), []byte(envContent), 0644)
		RequireNoError(t, err)

		config, err := LoadCoolifyConfigFromFile(filepath.Join(dir, ".env"))
		AssertNoError(t, err)
		AssertEqual(t, "https://coolify.example.com", config.URL)
		AssertEqual(t, "secret-token", config.Token)
	})

	t.Run("ignores comments", func(t *T) {
		dir := t.TempDir()
		envContent := `# This is a comment
COOLIFY_URL=https://coolify.example.com
# COOLIFY_TOKEN=wrong-token
COOLIFY_TOKEN=correct-token`

		err := os.WriteFile(filepath.Join(dir, ".env"), []byte(envContent), 0644)
		RequireNoError(t, err)

		config, err := LoadCoolifyConfigFromFile(filepath.Join(dir, ".env"))
		AssertNoError(t, err)
		AssertEqual(t, "correct-token", config.Token)
	})

	t.Run("ignores blank lines", func(t *T) {
		dir := t.TempDir()
		envContent := `COOLIFY_URL=https://coolify.example.com

COOLIFY_TOKEN=secret-token`

		err := os.WriteFile(filepath.Join(dir, ".env"), []byte(envContent), 0644)
		RequireNoError(t, err)

		config, err := LoadCoolifyConfigFromFile(filepath.Join(dir, ".env"))
		AssertNoError(t, err)
		AssertEqual(t, "https://coolify.example.com", config.URL)
	})
}

func TestPHP_LoadCoolifyConfigFromFile_Bad(t *T) {
	t.Run("fails when COOLIFY_URL missing", func(t *T) {
		dir := t.TempDir()
		envContent := `COOLIFY_TOKEN=secret-token`

		err := os.WriteFile(filepath.Join(dir, ".env"), []byte(envContent), 0644)
		RequireNoError(t, err)

		_, err = LoadCoolifyConfigFromFile(filepath.Join(dir, ".env"))
		AssertError(t, err)
		AssertContains(t, err.Error(), "COOLIFY_URL is not set")
	})

	t.Run("fails when COOLIFY_TOKEN missing", func(t *T) {
		dir := t.TempDir()
		envContent := `COOLIFY_URL=https://coolify.example.com`

		err := os.WriteFile(filepath.Join(dir, ".env"), []byte(envContent), 0644)
		RequireNoError(t, err)

		_, err = LoadCoolifyConfigFromFile(filepath.Join(dir, ".env"))
		AssertError(t, err)
		AssertContains(t, err.Error(), "COOLIFY_TOKEN is not set")
	})
}

func TestPHP_LoadCoolifyConfig_FromDirectory_Good(t *T) {
	t.Run("loads from directory", func(t *T) {
		dir := t.TempDir()
		envContent := `COOLIFY_URL=https://coolify.example.com
COOLIFY_TOKEN=secret-token`

		err := os.WriteFile(filepath.Join(dir, ".env"), []byte(envContent), 0644)
		RequireNoError(t, err)

		config, err := LoadCoolifyConfig(dir)
		AssertNoError(t, err)
		AssertEqual(t, "https://coolify.example.com", config.URL)
	})
}

func TestPHP_ValidateCoolifyConfig_Bad(t *T) {
	t.Run("returns error for empty URL", func(t *T) {
		config := &CoolifyConfig{Token: "token"}
		_, err := validateCoolifyConfig(config)
		AssertError(t, err)
		AssertContains(t, err.Error(), "COOLIFY_URL is not set")
	})

	t.Run("returns error for empty token", func(t *T) {
		config := &CoolifyConfig{URL: "https://coolify.example.com"}
		_, err := validateCoolifyConfig(config)
		AssertError(t, err)
		AssertContains(t, err.Error(), "COOLIFY_TOKEN is not set")
	})
}

func TestPHP_CoolifyClient_TriggerDeploy_Good(t *T) {
	t.Run("triggers deployment successfully", func(t *T) {
		server := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
			AssertEqual(t, "/api/v1/applications/app-123/deploy", r.URL.Path)
			AssertEqual(t, "POST", r.Method)
			AssertEqual(t, "Bearer secret-token", r.Header.Get("Authorization"))
			AssertEqual(t, "application/json", r.Header.Get("Content-Type"))

			resp := CoolifyDeployment{
				ID:        "dep-456",
				Status:    "queued",
				CreatedAt: time.Now(),
			}
			_ = json.NewEncoder(w).Encode(resp)
		}))
		defer server.Close()

		client := NewCoolifyClient(server.URL, "secret-token")
		deployment, err := client.TriggerDeploy(context.Background(), "app-123", false)

		AssertNoError(t, err)
		AssertEqual(t, "dep-456", deployment.ID)
		AssertEqual(t, "queued", deployment.Status)
	})

	t.Run("triggers deployment with force", func(t *T) {
		server := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
			var body map[string]interface{}
			_ = json.NewDecoder(r.Body).Decode(&body)
			AssertEqual(t, true, body["force"])

			resp := CoolifyDeployment{ID: "dep-456", Status: "queued"}
			_ = json.NewEncoder(w).Encode(resp)
		}))
		defer server.Close()

		client := NewCoolifyClient(server.URL, "secret-token")
		_, err := client.TriggerDeploy(context.Background(), "app-123", true)
		AssertNoError(t, err)
	})

	t.Run("handles minimal response", func(t *T) {
		server := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
			// Return an invalid JSON response to trigger the fallback
			_, _ = w.Write([]byte("not json"))
		}))
		defer server.Close()

		client := NewCoolifyClient(server.URL, "secret-token")
		deployment, err := client.TriggerDeploy(context.Background(), "app-123", false)

		AssertNoError(t, err)
		// The fallback response should be returned
		AssertEqual(t, "queued", deployment.Status)
	})
}

func TestPHP_CoolifyClient_TriggerDeploy_Bad(t *T) {
	t.Run("fails on HTTP error", func(t *T) {
		server := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
			w.WriteHeader(http.StatusInternalServerError)
			_ = json.NewEncoder(w).Encode(map[string]string{"message": "Internal error"})
		}))
		defer server.Close()

		client := NewCoolifyClient(server.URL, "secret-token")
		_, err := client.TriggerDeploy(context.Background(), "app-123", false)

		AssertError(t, err)
		AssertContains(t, err.Error(), "API error")
	})
}

func TestPHP_CoolifyClient_GetDeployment_Good(t *T) {
	t.Run("gets deployment details", func(t *T) {
		server := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
			AssertEqual(t, "/api/v1/applications/app-123/deployments/dep-456", r.URL.Path)
			AssertEqual(t, "GET", r.Method)

			resp := CoolifyDeployment{
				ID:        "dep-456",
				Status:    "finished",
				CommitSHA: "abc123",
				Branch:    "main",
			}
			_ = json.NewEncoder(w).Encode(resp)
		}))
		defer server.Close()

		client := NewCoolifyClient(server.URL, "secret-token")
		deployment, err := client.GetDeployment(context.Background(), "app-123", "dep-456")

		AssertNoError(t, err)
		AssertEqual(t, "dep-456", deployment.ID)
		AssertEqual(t, "finished", deployment.Status)
		AssertEqual(t, "abc123", deployment.CommitSHA)
	})
}

func TestPHP_CoolifyClient_GetDeployment_Bad(t *T) {
	t.Run("fails on 404", func(t *T) {
		server := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
			w.WriteHeader(http.StatusNotFound)
			_ = json.NewEncoder(w).Encode(map[string]string{"error": "Not found"})
		}))
		defer server.Close()

		client := NewCoolifyClient(server.URL, "secret-token")
		_, err := client.GetDeployment(context.Background(), "app-123", "dep-456")

		AssertError(t, err)
		AssertContains(t, err.Error(), "Not found")
	})
}

func TestPHP_CoolifyClient_ListDeployments_Good(t *T) {
	t.Run("lists deployments", func(t *T) {
		server := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
			AssertEqual(t, "/api/v1/applications/app-123/deployments", r.URL.Path)
			AssertEqual(t, "10", r.URL.Query().Get("limit"))

			resp := []CoolifyDeployment{
				{ID: "dep-1", Status: "finished"},
				{ID: "dep-2", Status: "failed"},
			}
			_ = json.NewEncoder(w).Encode(resp)
		}))
		defer server.Close()

		client := NewCoolifyClient(server.URL, "secret-token")
		deployments, err := client.ListDeployments(context.Background(), "app-123", 10)

		AssertNoError(t, err)
		AssertLen(t, deployments, 2)
		AssertEqual(t, "dep-1", deployments[0].ID)
		AssertEqual(t, "dep-2", deployments[1].ID)
	})

	t.Run("lists without limit", func(t *T) {
		server := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
			AssertEqual(t, "", r.URL.Query().Get("limit"))
			_ = json.NewEncoder(w).Encode([]CoolifyDeployment{})
		}))
		defer server.Close()

		client := NewCoolifyClient(server.URL, "secret-token")
		_, err := client.ListDeployments(context.Background(), "app-123", 0)
		AssertNoError(t, err)
	})
}

func TestPHP_CoolifyClient_Rollback_Good(t *T) {
	t.Run("triggers rollback", func(t *T) {
		server := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
			AssertEqual(t, "/api/v1/applications/app-123/rollback", r.URL.Path)
			AssertEqual(t, "POST", r.Method)

			var body map[string]string
			_ = json.NewDecoder(r.Body).Decode(&body)
			AssertEqual(t, "dep-old", body["deployment_id"])

			resp := CoolifyDeployment{
				ID:     "dep-new",
				Status: "rolling_back",
			}
			_ = json.NewEncoder(w).Encode(resp)
		}))
		defer server.Close()

		client := NewCoolifyClient(server.URL, "secret-token")
		deployment, err := client.Rollback(context.Background(), "app-123", "dep-old")

		AssertNoError(t, err)
		AssertEqual(t, "dep-new", deployment.ID)
		AssertEqual(t, "rolling_back", deployment.Status)
	})
}

func TestPHP_CoolifyClient_GetApp_Good(t *T) {
	t.Run("gets app details", func(t *T) {
		server := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
			AssertEqual(t, "/api/v1/applications/app-123", r.URL.Path)
			AssertEqual(t, "GET", r.Method)

			resp := CoolifyApp{
				ID:     "app-123",
				Name:   "MyApp",
				FQDN:   "https://myapp.example.com",
				Status: "running",
			}
			_ = json.NewEncoder(w).Encode(resp)
		}))
		defer server.Close()

		client := NewCoolifyClient(server.URL, "secret-token")
		app, err := client.GetApp(context.Background(), "app-123")

		AssertNoError(t, err)
		AssertEqual(t, "app-123", app.ID)
		AssertEqual(t, "MyApp", app.Name)
		AssertEqual(t, "https://myapp.example.com", app.FQDN)
	})
}

func TestCoolifyClient_SetHeaders(t *T) {
	t.Run("sets all required headers", func(t *T) {
		client := NewCoolifyClient("https://coolify.example.com", "my-token")
		req, _ := http.NewRequest("GET", "https://coolify.example.com", nil)

		client.setHeaders(req)

		AssertEqual(t, "Bearer my-token", req.Header.Get("Authorization"))
		AssertEqual(t, "application/json", req.Header.Get("Content-Type"))
		AssertEqual(t, "application/json", req.Header.Get("Accept"))
	})
}

func TestCoolifyClient_ParseError(t *T) {
	t.Run("parses message field", func(t *T) {
		server := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
			w.WriteHeader(http.StatusBadRequest)
			_ = json.NewEncoder(w).Encode(map[string]string{"message": "Bad request message"})
		}))
		defer server.Close()

		client := NewCoolifyClient(server.URL, "token")
		_, err := client.GetApp(context.Background(), "app-123")

		AssertError(t, err)
		AssertContains(t, err.Error(), "Bad request message")
	})

	t.Run("parses error field", func(t *T) {
		server := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
			w.WriteHeader(http.StatusBadRequest)
			_ = json.NewEncoder(w).Encode(map[string]string{"error": "Error message"})
		}))
		defer server.Close()

		client := NewCoolifyClient(server.URL, "token")
		_, err := client.GetApp(context.Background(), "app-123")

		AssertError(t, err)
		AssertContains(t, err.Error(), "Error message")
	})

	t.Run("returns raw body when no JSON fields", func(t *T) {
		server := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
			w.WriteHeader(http.StatusInternalServerError)
			_, _ = w.Write([]byte("Raw error message"))
		}))
		defer server.Close()

		client := NewCoolifyClient(server.URL, "token")
		_, err := client.GetApp(context.Background(), "app-123")

		AssertError(t, err)
		AssertContains(t, err.Error(), "Raw error message")
	})
}

func TestEnvironmentVariablePriority(t *T) {
	t.Run("env vars take precedence over .env file", func(t *T) {
		dir := t.TempDir()
		envContent := `COOLIFY_URL=https://from-file.com
COOLIFY_TOKEN=file-token`

		err := os.WriteFile(filepath.Join(dir, ".env"), []byte(envContent), 0644)
		RequireNoError(t, err)

		// Set environment variables
		origURL := os.Getenv("COOLIFY_URL")
		origToken := os.Getenv("COOLIFY_TOKEN")
		defer func() {
			_ = os.Setenv("COOLIFY_URL", origURL)
			_ = os.Setenv("COOLIFY_TOKEN", origToken)
		}()

		_ = os.Setenv("COOLIFY_URL", "https://from-env.com")
		_ = os.Setenv("COOLIFY_TOKEN", "env-token")

		config, err := LoadCoolifyConfig(dir)
		AssertNoError(t, err)
		// Environment variables should take precedence
		AssertEqual(t, "https://from-env.com", config.URL)
		AssertEqual(t, "env-token", config.Token)
	})
}
