package php

import (
	"encoding/json"
	"os"
	"path/filepath"
)

func TestPHP_ReadComposerJSON_Good(t *T) {
	t.Run("reads valid composer.json", func(t *T) {
		dir := t.TempDir()
		composerJSON := `{
			"name": "test/project",
			"require": {
				"php": "^8.2"
			}
		}`
		err := os.WriteFile(filepath.Join(dir, composerJSONFile), []byte(composerJSON), 0644)
		RequireNoError(t, err)

		raw, err := readComposerJSON(dir)
		AssertNoError(t, err)
		AssertNotNil(t, raw)
		AssertContains(t, string(raw["name"]), "test/project")
	})

	t.Run("preserves all fields", func(t *T) {
		dir := t.TempDir()
		composerJSON := `{
			"name": "test/project",
			"description": "Test project",
			"require": {"php": "^8.2"},
			"autoload": {"psr-4": {"App\\": "src/"}}
		}`
		err := os.WriteFile(filepath.Join(dir, composerJSONFile), []byte(composerJSON), 0644)
		RequireNoError(t, err)

		raw, err := readComposerJSON(dir)
		AssertNoError(t, err)
		AssertContains(t, string(raw["autoload"]), "psr-4")
	})
}

func TestPHP_ReadComposerJSON_Bad(t *T) {
	t.Run("missing composer.json", func(t *T) {
		dir := t.TempDir()
		_, err := readComposerJSON(dir)
		AssertError(t, err)
		AssertContains(t, err.Error(), "failed to read composer.json")
	})

	t.Run("invalid JSON", func(t *T) {
		dir := t.TempDir()
		err := os.WriteFile(filepath.Join(dir, composerJSONFile), []byte("not json{"), 0644)
		RequireNoError(t, err)

		_, err = readComposerJSON(dir)
		AssertError(t, err)
		AssertContains(t, err.Error(), "failed to parse composer.json")
	})
}

func TestPHP_WriteComposerJSON_Good(t *T) {
	t.Run("writes valid composer.json", func(t *T) {
		dir := t.TempDir()
		raw := make(map[string]json.RawMessage)
		raw["name"] = json.RawMessage(`"test/project"`)

		err := writeComposerJSON(dir, raw)
		AssertNoError(t, err)

		// Verify file was written
		content, err := os.ReadFile(filepath.Join(dir, composerJSONFile))
		AssertNoError(t, err)
		AssertContains(t, string(content), "test/project")
		// Verify trailing newline
		AssertTrue(t, content[len(content)-1] == '\n')
	})

	t.Run("pretty prints with indentation", func(t *T) {
		dir := t.TempDir()
		raw := make(map[string]json.RawMessage)
		raw["name"] = json.RawMessage(`"test/project"`)
		raw["require"] = json.RawMessage(`{"php":"^8.2"}`)

		err := writeComposerJSON(dir, raw)
		AssertNoError(t, err)

		content, err := os.ReadFile(filepath.Join(dir, composerJSONFile))
		AssertNoError(t, err)
		// Should be indented
		AssertContains(t, string(content), "    ")
	})
}

func TestPHP_WriteComposerJSON_Bad(t *T) {
	t.Run("fails for non-existent directory", func(t *T) {
		raw := make(map[string]json.RawMessage)
		raw["name"] = json.RawMessage(`"test/project"`)

		err := writeComposerJSON("/non/existent/path", raw)
		AssertError(t, err)
		AssertContains(t, err.Error(), "failed to write composer.json")
	})
}
func TestPHP_GetRepositories_Good(t *T) {
	t.Run("returns empty slice when no repositories", func(t *T) {
		raw := make(map[string]json.RawMessage)
		raw["name"] = json.RawMessage(`"test/project"`)

		repos, err := getRepositories(raw)
		AssertNoError(t, err)
		AssertEmpty(t, repos)
	})

	t.Run("parses existing repositories", func(t *T) {
		raw := make(map[string]json.RawMessage)
		raw["name"] = json.RawMessage(`"test/project"`)
		raw["repositories"] = json.RawMessage(`[{"type":"path","url":"` + testPackagePath + `"}]`)

		repos, err := getRepositories(raw)
		AssertNoError(t, err)
		AssertLen(t, repos, 1)
		AssertEqual(t, "path", repos[0].Type)
		AssertEqual(t, testPackagePath, repos[0].URL)
	})

	t.Run("parses repositories with options", func(t *T) {
		raw := make(map[string]json.RawMessage)
		raw["repositories"] = json.RawMessage(`[{"type":"path","url":"/path","options":{"symlink":true}}]`)

		repos, err := getRepositories(raw)
		AssertNoError(t, err)
		AssertLen(t, repos, 1)
		AssertNotNil(t, repos[0].Options)
		AssertEqual(t, true, repos[0].Options["symlink"])
	})
}

func TestPHP_GetRepositories_Bad(t *T) {
	t.Run("fails for invalid repositories JSON", func(t *T) {
		raw := make(map[string]json.RawMessage)
		raw["repositories"] = json.RawMessage(`not valid json`)

		_, err := getRepositories(raw)
		AssertError(t, err)
		AssertContains(t, err.Error(), "failed to parse repositories")
	})
}

func TestPHP_SetRepositories_Good(t *T) {
	t.Run("sets repositories", func(t *T) {
		raw := make(map[string]json.RawMessage)
		repos := []composerRepository{
			{Type: "path", URL: testPackagePath},
		}

		err := setRepositories(raw, repos)
		AssertNoError(t, err)
		AssertContains(t, string(raw["repositories"]), testPackagePath)
	})

	t.Run("removes repositories key when empty", func(t *T) {
		raw := make(map[string]json.RawMessage)
		raw["repositories"] = json.RawMessage(`[{"type":"path"}]`)

		err := setRepositories(raw, []composerRepository{})
		AssertNoError(t, err)
		_, exists := raw["repositories"]
		AssertFalse(t, exists)
	})
}

func TestPHP_GetPackageInfo_Good(t *T) {
	t.Run("extracts package name and version", func(t *T) {
		dir := t.TempDir()
		composerJSON := `{
			"name": "` + testVendorPackage + `",
			"version": "1.0.0"
		}`
		err := os.WriteFile(filepath.Join(dir, composerJSONFile), []byte(composerJSON), 0644)
		RequireNoError(t, err)

		name, version, err := getPackageInfo(dir)
		AssertNoError(t, err)
		AssertEqual(t, testVendorPackage, name)
		AssertEqual(t, "1.0.0", version)
	})

	t.Run("works without version", func(t *T) {
		dir := t.TempDir()
		composerJSON := `{
			"name": "` + testVendorPackage + `"
		}`
		err := os.WriteFile(filepath.Join(dir, composerJSONFile), []byte(composerJSON), 0644)
		RequireNoError(t, err)

		name, version, err := getPackageInfo(dir)
		AssertNoError(t, err)
		AssertEqual(t, testVendorPackage, name)
		AssertEqual(t, "", version)
	})
}

func TestPHP_GetPackageInfo_Bad(t *T) {
	t.Run("missing composer.json", func(t *T) {
		dir := t.TempDir()
		_, _, err := getPackageInfo(dir)
		AssertError(t, err)
		AssertContains(t, err.Error(), "failed to read package composer.json")
	})

	t.Run("invalid JSON", func(t *T) {
		dir := t.TempDir()
		err := os.WriteFile(filepath.Join(dir, composerJSONFile), []byte("not json{"), 0644)
		RequireNoError(t, err)

		_, _, err = getPackageInfo(dir)
		AssertError(t, err)
		AssertContains(t, err.Error(), "failed to parse package composer.json")
	})

	t.Run("missing name", func(t *T) {
		dir := t.TempDir()
		composerJSON := `{"version": "1.0.0"}`
		err := os.WriteFile(filepath.Join(dir, composerJSONFile), []byte(composerJSON), 0644)
		RequireNoError(t, err)

		_, _, err = getPackageInfo(dir)
		AssertError(t, err)
		AssertContains(t, err.Error(), "package name not found")
	})
}

func TestPHP_LinkPackages_Good(t *T) {
	t.Run("links a package", func(t *T) {
		// Create project directory
		projectDir := t.TempDir()
		err := os.WriteFile(filepath.Join(projectDir, composerJSONFile), []byte(`{"name":"test/project"}`), 0644)
		RequireNoError(t, err)

		// Create package directory
		packageDir := t.TempDir()
		err = os.WriteFile(filepath.Join(packageDir, composerJSONFile), []byte(`{"name":"`+testVendorPackage+`"}`), 0644)
		RequireNoError(t, err)

		err = LinkPackages(projectDir, []string{packageDir})
		AssertNoError(t, err)

		// Verify repository was added
		raw, err := readComposerJSON(projectDir)
		AssertNoError(t, err)
		repos, err := getRepositories(raw)
		AssertNoError(t, err)
		AssertLen(t, repos, 1)
		AssertEqual(t, "path", repos[0].Type)
	})

	t.Run("skips already linked package", func(t *T) {
		// Create project with existing repository
		projectDir := t.TempDir()
		packageDir := t.TempDir()

		err := os.WriteFile(filepath.Join(packageDir, composerJSONFile), []byte(`{"name":"`+testVendorPackage+`"}`), 0644)
		RequireNoError(t, err)

		absPackagePath, _ := filepath.Abs(packageDir)
		composerJSON := `{
			"name": "test/project",
			"repositories": [{"type":"path","url":"` + absPackagePath + `"}]
		}`
		err = os.WriteFile(filepath.Join(projectDir, composerJSONFile), []byte(composerJSON), 0644)
		RequireNoError(t, err)

		// Link again - should not add duplicate
		err = LinkPackages(projectDir, []string{packageDir})
		AssertNoError(t, err)

		raw, err := readComposerJSON(projectDir)
		AssertNoError(t, err)
		repos, err := getRepositories(raw)
		AssertNoError(t, err)
		AssertLen(t, repos, 1) // Still only one
	})

	t.Run("links multiple packages", func(t *T) {
		projectDir := t.TempDir()
		err := os.WriteFile(filepath.Join(projectDir, composerJSONFile), []byte(`{"name":"test/project"}`), 0644)
		RequireNoError(t, err)

		pkg1Dir := t.TempDir()
		err = os.WriteFile(filepath.Join(pkg1Dir, composerJSONFile), []byte(`{"name":"vendor/pkg1"}`), 0644)
		RequireNoError(t, err)

		pkg2Dir := t.TempDir()
		err = os.WriteFile(filepath.Join(pkg2Dir, composerJSONFile), []byte(`{"name":"vendor/pkg2"}`), 0644)
		RequireNoError(t, err)

		err = LinkPackages(projectDir, []string{pkg1Dir, pkg2Dir})
		AssertNoError(t, err)

		raw, err := readComposerJSON(projectDir)
		AssertNoError(t, err)
		repos, err := getRepositories(raw)
		AssertNoError(t, err)
		AssertLen(t, repos, 2)
	})
}

func TestPHP_LinkPackages_Bad(t *T) {
	t.Run(testFailsNonPHPProject, func(t *T) {
		dir := t.TempDir()
		err := LinkPackages(dir, []string{testPackagePath})
		AssertError(t, err)
		AssertContains(t, err.Error(), testNotPHPProject)
	})

	t.Run("fails for non-PHP package", func(t *T) {
		projectDir := t.TempDir()
		err := os.WriteFile(filepath.Join(projectDir, composerJSONFile), []byte(`{"name":"test/project"}`), 0644)
		RequireNoError(t, err)

		packageDir := t.TempDir()
		// No composer.json in package

		err = LinkPackages(projectDir, []string{packageDir})
		AssertError(t, err)
		AssertContains(t, err.Error(), "not a PHP package")
	})
}

func TestPHP_UnlinkPackages_Good(t *T) {
	t.Run("unlinks package by name", func(t *T) {
		projectDir := t.TempDir()
		packageDir := t.TempDir()

		err := os.WriteFile(filepath.Join(packageDir, composerJSONFile), []byte(`{"name":"`+testVendorPackage+`"}`), 0644)
		RequireNoError(t, err)

		absPackagePath, _ := filepath.Abs(packageDir)
		composerJSON := `{
			"name": "test/project",
			"repositories": [{"type":"path","url":"` + absPackagePath + `"}]
		}`
		err = os.WriteFile(filepath.Join(projectDir, composerJSONFile), []byte(composerJSON), 0644)
		RequireNoError(t, err)

		err = UnlinkPackages(projectDir, []string{testVendorPackage})
		AssertNoError(t, err)

		raw, err := readComposerJSON(projectDir)
		AssertNoError(t, err)
		repos, err := getRepositories(raw)
		AssertNoError(t, err)
		AssertLen(t, repos, 0)
	})

	t.Run("unlinks package by path", func(t *T) {
		projectDir := t.TempDir()
		packageDir := t.TempDir()

		absPackagePath, _ := filepath.Abs(packageDir)
		composerJSON := `{
			"name": "test/project",
			"repositories": [{"type":"path","url":"` + absPackagePath + `"}]
		}`
		err := os.WriteFile(filepath.Join(projectDir, composerJSONFile), []byte(composerJSON), 0644)
		RequireNoError(t, err)

		err = UnlinkPackages(projectDir, []string{absPackagePath})
		AssertNoError(t, err)

		raw, err := readComposerJSON(projectDir)
		AssertNoError(t, err)
		repos, err := getRepositories(raw)
		AssertNoError(t, err)
		AssertLen(t, repos, 0)
	})

	t.Run("keeps non-path repositories", func(t *T) {
		projectDir := t.TempDir()
		composerJSON := `{
			"name": "test/project",
			"repositories": [
				{"type":"vcs","url":"https://github.com/vendor/package"},
				{"type":"path","url":"/local/path"}
			]
		}`
		err := os.WriteFile(filepath.Join(projectDir, composerJSONFile), []byte(composerJSON), 0644)
		RequireNoError(t, err)

		err = UnlinkPackages(projectDir, []string{"/local/path"})
		AssertNoError(t, err)

		raw, err := readComposerJSON(projectDir)
		AssertNoError(t, err)
		repos, err := getRepositories(raw)
		AssertNoError(t, err)
		AssertLen(t, repos, 1)
		AssertEqual(t, "vcs", repos[0].Type)
	})
}

func TestPHP_UnlinkPackages_Bad(t *T) {
	t.Run(testFailsNonPHPProject, func(t *T) {
		dir := t.TempDir()
		err := UnlinkPackages(dir, []string{testVendorPackage})
		AssertError(t, err)
		AssertContains(t, err.Error(), testNotPHPProject)
	})
}

func TestPHP_ListLinkedPackages_Good(t *T) {
	t.Run("lists linked packages", func(t *T) {
		projectDir := t.TempDir()
		packageDir := t.TempDir()

		err := os.WriteFile(filepath.Join(packageDir, composerJSONFile), []byte(`{"name":"`+testVendorPackage+`","version":"1.0.0"}`), 0644)
		RequireNoError(t, err)

		absPackagePath, _ := filepath.Abs(packageDir)
		composerJSON := `{
			"name": "test/project",
			"repositories": [{"type":"path","url":"` + absPackagePath + `"}]
		}`
		err = os.WriteFile(filepath.Join(projectDir, composerJSONFile), []byte(composerJSON), 0644)
		RequireNoError(t, err)

		linked, err := ListLinkedPackages(projectDir)
		AssertNoError(t, err)
		AssertLen(t, linked, 1)
		AssertEqual(t, testVendorPackage, linked[0].Name)
		AssertEqual(t, "1.0.0", linked[0].Version)
		AssertEqual(t, absPackagePath, linked[0].Path)
	})

	t.Run("returns empty list when no linked packages", func(t *T) {
		projectDir := t.TempDir()
		err := os.WriteFile(filepath.Join(projectDir, composerJSONFile), []byte(`{"name":"test/project"}`), 0644)
		RequireNoError(t, err)

		linked, err := ListLinkedPackages(projectDir)
		AssertNoError(t, err)
		AssertEmpty(t, linked)
	})

	t.Run("uses basename when package info unavailable", func(t *T) {
		projectDir := t.TempDir()
		composerJSON := `{
			"name": "test/project",
			"repositories": [{"type":"path","url":"/nonexistent/package-name"}]
		}`
		err := os.WriteFile(filepath.Join(projectDir, composerJSONFile), []byte(composerJSON), 0644)
		RequireNoError(t, err)

		linked, err := ListLinkedPackages(projectDir)
		AssertNoError(t, err)
		AssertLen(t, linked, 1)
		AssertEqual(t, "package-name", linked[0].Name)
	})

	t.Run("ignores non-path repositories", func(t *T) {
		projectDir := t.TempDir()
		composerJSON := `{
			"name": "test/project",
			"repositories": [
				{"type":"vcs","url":"https://github.com/vendor/package"}
			]
		}`
		err := os.WriteFile(filepath.Join(projectDir, composerJSONFile), []byte(composerJSON), 0644)
		RequireNoError(t, err)

		linked, err := ListLinkedPackages(projectDir)
		AssertNoError(t, err)
		AssertEmpty(t, linked)
	})
}

func TestPHP_ListLinkedPackages_Bad(t *T) {
	t.Run(testFailsNonPHPProject, func(t *T) {
		dir := t.TempDir()
		_, err := ListLinkedPackages(dir)
		AssertError(t, err)
		AssertContains(t, err.Error(), testNotPHPProject)
	})
}

func TestPHP_UpdatePackages_Bad(t *T) {
	t.Run(testFailsNonPHPProject, func(t *T) {
		dir := t.TempDir()
		err := UpdatePackages(dir, []string{testVendorPackage})
		AssertError(t, err)
		AssertContains(t, err.Error(), testNotPHPProject)
	})
}

func TestPHP_UpdatePackages_Good(t *T) {
	t.Skip("requires Composer installed")

	t.Run("runs composer update", func(t *T) {
		projectDir := t.TempDir()
		err := os.WriteFile(filepath.Join(projectDir, composerJSONFile), []byte(`{"name":"test/project"}`), 0644)
		RequireNoError(t, err)

		_ = UpdatePackages(projectDir, []string{testVendorPackage})
		// This will fail because composer update needs real dependencies
		// but it validates the command runs
	})
}

func TestLinkedPackage_Struct(t *T) {
	t.Run(testAllFieldsAccessible, func(t *T) {
		pkg := LinkedPackage{
			Name:    testVendorPackage,
			Path:    testPackagePath,
			Version: "1.0.0",
		}

		AssertEqual(t, testVendorPackage, pkg.Name)
		AssertEqual(t, testPackagePath, pkg.Path)
		AssertEqual(t, "1.0.0", pkg.Version)
	})
}

func TestComposerRepository_Struct(t *T) {
	t.Run(testAllFieldsAccessible, func(t *T) {
		repo := composerRepository{
			Type: "path",
			URL:  testPackagePath,
			Options: map[string]any{
				"symlink": true,
			},
		}

		AssertEqual(t, "path", repo.Type)
		AssertEqual(t, testPackagePath, repo.URL)
		AssertEqual(t, true, repo.Options["symlink"])
	})
}
