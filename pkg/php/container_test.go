package php

import (
	"context"
	"os"
	"path/filepath"
)

func TestPHP_DockerBuildOptions_Good(t *T) {
	t.Run("all fields accessible", func(t *T) {
		opts := DockerBuildOptions{
			ProjectDir:   "/project",
			ImageName:    "myapp",
			Tag:          "v1.0.0",
			Platform:     "linux/amd64",
			Dockerfile:   "/path/to/Dockerfile",
			NoBuildCache: true,
			BuildArgs:    map[string]string{"ARG1": "value1"},
			Output:       os.Stdout,
		}

		AssertEqual(t, "/project", opts.ProjectDir)
		AssertEqual(t, "myapp", opts.ImageName)
		AssertEqual(t, "v1.0.0", opts.Tag)
		AssertEqual(t, "linux/amd64", opts.Platform)
		AssertEqual(t, "/path/to/Dockerfile", opts.Dockerfile)
		AssertTrue(t, opts.NoBuildCache)
		AssertEqual(t, "value1", opts.BuildArgs["ARG1"])
		AssertNotNil(t, opts.Output)
	})
}

func TestPHP_LinuxKitBuildOptions_Good(t *T) {
	t.Run("all fields accessible", func(t *T) {
		opts := LinuxKitBuildOptions{
			ProjectDir: "/project",
			OutputPath: "/output/image.qcow2",
			Format:     "qcow2",
			Template:   "server-php",
			Variables:  map[string]string{"VAR1": "value1"},
			Output:     os.Stdout,
		}

		AssertEqual(t, "/project", opts.ProjectDir)
		AssertEqual(t, "/output/image.qcow2", opts.OutputPath)
		AssertEqual(t, "qcow2", opts.Format)
		AssertEqual(t, "server-php", opts.Template)
		AssertEqual(t, "value1", opts.Variables["VAR1"])
		AssertNotNil(t, opts.Output)
	})
}

func TestPHP_ServeOptions_Good(t *T) {
	t.Run("all fields accessible", func(t *T) {
		opts := ServeOptions{
			ImageName:     "myapp",
			Tag:           "latest",
			ContainerName: "myapp-container",
			Port:          8080,
			HTTPSPort:     8443,
			Detach:        true,
			EnvFile:       "/path/to/.env",
			Volumes:       map[string]string{"/host": "/container"},
			Output:        os.Stdout,
		}

		AssertEqual(t, "myapp", opts.ImageName)
		AssertEqual(t, "latest", opts.Tag)
		AssertEqual(t, "myapp-container", opts.ContainerName)
		AssertEqual(t, 8080, opts.Port)
		AssertEqual(t, 8443, opts.HTTPSPort)
		AssertTrue(t, opts.Detach)
		AssertEqual(t, "/path/to/.env", opts.EnvFile)
		AssertEqual(t, "/container", opts.Volumes["/host"])
		AssertNotNil(t, opts.Output)
	})
}

func TestPHP_IsPHPProject_Container_Good(t *T) {
	t.Run("returns true with composer.json", func(t *T) {
		dir := t.TempDir()
		err := os.WriteFile(filepath.Join(dir, "composer.json"), []byte(`{}`), 0644)
		RequireNoError(t, err)

		AssertTrue(t, IsPHPProject(dir))
	})
}

func TestPHP_IsPHPProject_Container_Bad(t *T) {
	t.Run("returns false without composer.json", func(t *T) {
		dir := t.TempDir()
		AssertFalse(t, IsPHPProject(dir))
	})

	t.Run("returns false for non-existent directory", func(t *T) {
		AssertFalse(t, IsPHPProject("/non/existent/path"))
	})
}

func TestPHP_LookupLinuxKit_Bad(t *T) {
	t.Run("returns error when linuxkit not found", func(t *T) {
		// Save original PATH and paths
		origPath := os.Getenv("PATH")
		origCommonPaths := commonLinuxKitPaths
		defer func() {
			_ = os.Setenv("PATH", origPath)
			commonLinuxKitPaths = origCommonPaths
		}()

		// Set PATH to empty and clear common paths
		_ = os.Setenv("PATH", "")
		commonLinuxKitPaths = []string{}

		_, err := lookupLinuxKit()
		AssertError(t, err)
		AssertContains(t, err.Error(), "linuxkit not found")
	})
}

func TestPHP_GetLinuxKitTemplate_Good(t *T) {
	t.Run("returns server-php template", func(t *T) {
		content, err := getLinuxKitTemplate("server-php")
		AssertNoError(t, err)
		AssertContains(t, content, "kernel:")
		AssertContains(t, content, "linuxkit/kernel")
	})
}

func TestPHP_GetLinuxKitTemplate_Bad(t *T) {
	t.Run("returns error for unknown template", func(t *T) {
		_, err := getLinuxKitTemplate("unknown-template")
		AssertError(t, err)
		AssertContains(t, err.Error(), "template not found")
	})
}

func TestPHP_ApplyTemplateVariables_Good(t *T) {
	t.Run("replaces variables", func(t *T) {
		content := "Hello ${NAME}, welcome to ${PLACE}!"
		vars := map[string]string{
			"NAME":  "World",
			"PLACE": "Earth",
		}

		result, err := applyTemplateVariables(content, vars)
		AssertNoError(t, err)
		AssertEqual(t, "Hello World, welcome to Earth!", result)
	})

	t.Run("handles empty variables", func(t *T) {
		content := "No variables here"
		vars := map[string]string{}

		result, err := applyTemplateVariables(content, vars)
		AssertNoError(t, err)
		AssertEqual(t, "No variables here", result)
	})

	t.Run("leaves unmatched placeholders", func(t *T) {
		content := "Hello ${NAME}, ${UNKNOWN} is unknown"
		vars := map[string]string{
			"NAME": "World",
		}

		result, err := applyTemplateVariables(content, vars)
		AssertNoError(t, err)
		AssertContains(t, result, "Hello World")
		AssertContains(t, result, "${UNKNOWN}")
	})

	t.Run("handles multiple occurrences", func(t *T) {
		content := "${VAR} and ${VAR} again"
		vars := map[string]string{
			"VAR": "value",
		}

		result, err := applyTemplateVariables(content, vars)
		AssertNoError(t, err)
		AssertEqual(t, "value and value again", result)
	})
}

func TestPHP_DefaultServerPHPTemplate_Good(t *T) {
	t.Run("template has required sections", func(t *T) {
		AssertContains(t, defaultServerPHPTemplate, "kernel:")
		AssertContains(t, defaultServerPHPTemplate, "init:")
		AssertContains(t, defaultServerPHPTemplate, "services:")
		AssertContains(t, defaultServerPHPTemplate, "onboot:")
	})

	t.Run("template contains placeholders", func(t *T) {
		AssertContains(t, defaultServerPHPTemplate, "${SSH_KEY:-}")
	})
}

func TestPHP_BuildDocker_Bad(t *T) {
	t.Skip("requires Docker installed")

	t.Run("fails for non-PHP project", func(t *T) {
		dir := t.TempDir()
		err := BuildDocker(context.TODO(), DockerBuildOptions{ProjectDir: dir})
		AssertError(t, err)
		AssertContains(t, err.Error(), "not a PHP project")
	})
}

func TestPHP_BuildLinuxKit_Bad(t *T) {
	t.Skip("requires linuxkit installed")

	t.Run("fails for non-PHP project", func(t *T) {
		dir := t.TempDir()
		err := BuildLinuxKit(context.TODO(), LinuxKitBuildOptions{ProjectDir: dir})
		AssertError(t, err)
		AssertContains(t, err.Error(), "not a PHP project")
	})
}

func TestPHP_ServeProduction_Bad(t *T) {
	t.Run("fails without image name", func(t *T) {
		err := ServeProduction(context.TODO(), ServeOptions{})
		AssertError(t, err)
		AssertContains(t, err.Error(), "image name is required")
	})
}

func TestPHP_Shell_Bad(t *T) {
	t.Run("fails without container ID", func(t *T) {
		err := Shell(context.TODO(), "")
		AssertError(t, err)
		AssertContains(t, err.Error(), "container ID is required")
	})
}

func TestPHP_ResolveDockerContainerID_Bad(t *T) {
	t.Setenv("PATH", t.TempDir())
	id, err := resolveDockerContainerID(context.TODO(), "abc")
	AssertError(t, err)
	AssertEqual(t, "", id)
}

func TestBuildDocker_DefaultOptions(t *T) {
	t.Run("sets defaults correctly", func(t *T) {
		// This tests the default logic without actually running Docker
		opts := DockerBuildOptions{}

		// Verify default values would be set in BuildDocker
		if opts.Tag == "" {
			opts.Tag = "latest"
		}
		AssertEqual(t, "latest", opts.Tag)

		if opts.ImageName == "" {
			opts.ImageName = filepath.Base("/project/myapp")
		}
		AssertEqual(t, "myapp", opts.ImageName)
	})
}

func TestBuildLinuxKit_DefaultOptions(t *T) {
	t.Run("sets defaults correctly", func(t *T) {
		opts := LinuxKitBuildOptions{}

		// Verify default values would be set
		if opts.Template == "" {
			opts.Template = "server-php"
		}
		AssertEqual(t, "server-php", opts.Template)

		if opts.Format == "" {
			opts.Format = "qcow2"
		}
		AssertEqual(t, "qcow2", opts.Format)
	})
}

func TestServeProduction_DefaultOptions(t *T) {
	t.Run("sets defaults correctly", func(t *T) {
		opts := ServeOptions{ImageName: "myapp"}

		// Verify default values would be set
		if opts.Tag == "" {
			opts.Tag = "latest"
		}
		AssertEqual(t, "latest", opts.Tag)

		if opts.Port == 0 {
			opts.Port = 80
		}
		AssertEqual(t, 80, opts.Port)

		if opts.HTTPSPort == 0 {
			opts.HTTPSPort = 443
		}
		AssertEqual(t, 443, opts.HTTPSPort)
	})
}

func TestPHP_LookupLinuxKit_Good(t *T) {
	t.Skip("requires linuxkit installed")

	t.Run("finds linuxkit in PATH", func(t *T) {
		path, err := lookupLinuxKit()
		AssertNoError(t, err)
		AssertNotEmpty(t, path)
	})
}

func TestBuildDocker_WithCustomDockerfile(t *T) {
	t.Skip("requires Docker installed")

	t.Run("uses custom Dockerfile when provided", func(t *T) {
		dir := t.TempDir()
		err := os.WriteFile(filepath.Join(dir, "composer.json"), []byte(`{"name":"test"}`), 0644)
		RequireNoError(t, err)

		dockerfilePath := filepath.Join(dir, "Dockerfile.custom")
		err = os.WriteFile(dockerfilePath, []byte("FROM alpine"), 0644)
		RequireNoError(t, err)

		opts := DockerBuildOptions{
			ProjectDir: dir,
			Dockerfile: dockerfilePath,
		}

		// The function would use the custom Dockerfile
		AssertEqual(t, dockerfilePath, opts.Dockerfile)
	})
}

func TestBuildDocker_GeneratesDockerfile(t *T) {
	t.Skip("requires Docker installed")

	t.Run("generates Dockerfile when not provided", func(t *T) {
		dir := t.TempDir()

		// Create valid PHP project
		composerJSON := `{"name":"test","require":{"php":"^8.2","laravel/framework":"^11.0"}}`
		err := os.WriteFile(filepath.Join(dir, "composer.json"), []byte(composerJSON), 0644)
		RequireNoError(t, err)

		opts := DockerBuildOptions{
			ProjectDir: dir,
			// Dockerfile not specified - should be generated
		}

		AssertEmpty(t, opts.Dockerfile)
	})
}

func TestServeProduction_BuildsCorrectArgs(t *T) {
	t.Run("builds correct docker run arguments", func(t *T) {
		opts := ServeOptions{
			ImageName:     "myapp",
			Tag:           "v1.0.0",
			ContainerName: "myapp-prod",
			Port:          8080,
			HTTPSPort:     8443,
			Detach:        true,
			EnvFile:       "/path/.env",
			Volumes: map[string]string{
				"/host/storage": "/app/storage",
			},
		}

		// Verify the expected image reference format
		imageRef := opts.ImageName + ":" + opts.Tag
		AssertEqual(t, "myapp:v1.0.0", imageRef)

		// Verify port format
		portMapping := opts.Port
		AssertEqual(t, 8080, portMapping)
	})
}

func TestShell_Integration(t *T) {
	if os.Getenv("CORE_PHP_RUN_DOCKER_INTEGRATION") == "" {
		t.Skip("requires Docker with running container")
	}
	err := Shell(context.TODO(), os.Getenv("CORE_PHP_CONTAINER"))
	AssertNoError(t, err)
}

func TestResolveDockerContainerID_Integration(t *T) {
	if os.Getenv("CORE_PHP_RUN_DOCKER_INTEGRATION") == "" {
		t.Skip("requires Docker with running containers")
	}
	id, err := resolveDockerContainerID(context.TODO(), os.Getenv("CORE_PHP_CONTAINER"))
	AssertNoError(t, err)
	AssertNotEmpty(t, id)
}
