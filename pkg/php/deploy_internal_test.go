package php

import (
	"time"
)

func TestPHP_ConvertDeployment_Good(t *T) {
	t.Run("converts all fields", func(t *T) {
		now := time.Now()
		coolify := &CoolifyDeployment{
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

		status := convertDeployment(coolify)

		AssertEqual(t, "dep-123", status.ID)
		AssertEqual(t, "finished", status.Status)
		AssertEqual(t, "https://app.example.com", status.URL)
		AssertEqual(t, "abc123", status.Commit)
		AssertEqual(t, "Test commit", status.CommitMessage)
		AssertEqual(t, "main", status.Branch)
		AssertEqual(t, now, status.StartedAt)
		AssertEqual(t, now.Add(5*time.Minute), status.CompletedAt)
		AssertEqual(t, "Build successful", status.Log)
	})

	t.Run("handles empty deployment", func(t *T) {
		coolify := &CoolifyDeployment{}
		status := convertDeployment(coolify)

		AssertEmpty(t, status.ID)
		AssertEmpty(t, status.Status)
	})
}

func TestPHP_DeploymentStatus_Struct_Good(t *T) {
	t.Run("all fields accessible", func(t *T) {
		now := time.Now()
		status := DeploymentStatus{
			ID:            "dep-123",
			Status:        "finished",
			URL:           "https://app.example.com",
			Commit:        "abc123",
			CommitMessage: "Test commit",
			Branch:        "main",
			StartedAt:     now,
			CompletedAt:   now.Add(5 * time.Minute),
			Log:           "Build log",
		}

		AssertEqual(t, "dep-123", status.ID)
		AssertEqual(t, "finished", status.Status)
		AssertEqual(t, "https://app.example.com", status.URL)
		AssertEqual(t, "abc123", status.Commit)
		AssertEqual(t, "Test commit", status.CommitMessage)
		AssertEqual(t, "main", status.Branch)
		AssertEqual(t, "Build log", status.Log)
	})
}

func TestPHP_DeployOptions_Struct_Good(t *T) {
	t.Run("all fields accessible", func(t *T) {
		opts := DeployOptions{
			Dir:          "/project",
			Environment:  EnvProduction,
			Force:        true,
			Wait:         true,
			WaitTimeout:  10 * time.Minute,
			PollInterval: 5 * time.Second,
		}

		AssertEqual(t, "/project", opts.Dir)
		AssertEqual(t, EnvProduction, opts.Environment)
		AssertTrue(t, opts.Force)
		AssertTrue(t, opts.Wait)
		AssertEqual(t, 10*time.Minute, opts.WaitTimeout)
		AssertEqual(t, 5*time.Second, opts.PollInterval)
	})
}

func TestPHP_StatusOptions_Struct_Good(t *T) {
	t.Run("all fields accessible", func(t *T) {
		opts := StatusOptions{
			Dir:          "/project",
			Environment:  EnvStaging,
			DeploymentID: "dep-123",
		}

		AssertEqual(t, "/project", opts.Dir)
		AssertEqual(t, EnvStaging, opts.Environment)
		AssertEqual(t, "dep-123", opts.DeploymentID)
	})
}

func TestPHP_RollbackOptions_Struct_Good(t *T) {
	t.Run("all fields accessible", func(t *T) {
		opts := RollbackOptions{
			Dir:          "/project",
			Environment:  EnvProduction,
			DeploymentID: "dep-old",
			Wait:         true,
			WaitTimeout:  5 * time.Minute,
		}

		AssertEqual(t, "/project", opts.Dir)
		AssertEqual(t, EnvProduction, opts.Environment)
		AssertEqual(t, "dep-old", opts.DeploymentID)
		AssertTrue(t, opts.Wait)
		AssertEqual(t, 5*time.Minute, opts.WaitTimeout)
	})
}

func TestEnvironment_Constants(t *T) {
	t.Run("constants are defined", func(t *T) {
		AssertEqual(t, Environment("production"), EnvProduction)
		AssertEqual(t, Environment("staging"), EnvStaging)
	})
}

func TestPHP_GetAppIDForEnvironment_Ugly(t *T) {
	t.Run("staging without staging ID falls back to production", func(t *T) {
		config := &CoolifyConfig{
			AppID: "prod-123",
			// No StagingAppID set
		}

		id := getAppIDForEnvironment(config, EnvStaging)
		AssertEqual(t, "prod-123", id)
	})

	t.Run("staging with staging ID uses staging", func(t *T) {
		config := &CoolifyConfig{
			AppID:        "prod-123",
			StagingAppID: "staging-456",
		}

		id := getAppIDForEnvironment(config, EnvStaging)
		AssertEqual(t, "staging-456", id)
	})

	t.Run("production uses production ID", func(t *T) {
		config := &CoolifyConfig{
			AppID:        "prod-123",
			StagingAppID: "staging-456",
		}

		id := getAppIDForEnvironment(config, EnvProduction)
		AssertEqual(t, "prod-123", id)
	})

	t.Run("unknown environment uses production", func(t *T) {
		config := &CoolifyConfig{
			AppID: "prod-123",
		}

		id := getAppIDForEnvironment(config, "unknown")
		AssertEqual(t, "prod-123", id)
	})
}

func TestPHP_IsDeploymentComplete_Ugly(t *T) {
	tests := []struct {
		status   string
		expected bool
	}{
		{"finished", true},
		{"success", true},
		{"failed", true},
		{"error", true},
		{"cancelled", true},
		{"queued", false},
		{"building", false},
		{"deploying", false},
		{"pending", false},
		{"rolling_back", false},
		{"", false},
		{"unknown", false},
	}

	for _, tt := range tests {
		t.Run(tt.status, func(t *T) {
			result := IsDeploymentComplete(tt.status)
			AssertEqual(t, tt.expected, result)
		})
	}
}

func TestPHP_IsDeploymentSuccessful_Ugly(t *T) {
	tests := []struct {
		status   string
		expected bool
	}{
		{"finished", true},
		{"success", true},
		{"failed", false},
		{"error", false},
		{"cancelled", false},
		{"queued", false},
		{"building", false},
		{"deploying", false},
		{"", false},
	}

	for _, tt := range tests {
		t.Run(tt.status, func(t *T) {
			result := IsDeploymentSuccessful(tt.status)
			AssertEqual(t, tt.expected, result)
		})
	}
}
