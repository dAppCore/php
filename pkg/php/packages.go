package php

import (
	"encoding/json"
	"os"
	"os/exec"
	"path/filepath"

	"dappco.re/go/cli/pkg/cli"
)

// LinkedPackage represents a linked local package.
type LinkedPackage struct {
	Name    string `json:"name"`
	Path    string `json:"path"`
	Version string `json:"version"`
}

// composerRepository represents a composer repository entry.
type composerRepository struct {
	Type    string         `json:"type"`
	URL     string         `json:"url,omitempty"`
	Options map[string]any `json:"options,omitempty"`
}

// readComposerJSON reads and parses composer.json from the given directory.
func readComposerJSON(dir string) (map[string]json.RawMessage, error) {
	m := getMedium()
	composerPath := filepath.Join(dir, composerJSONFile)
	content, err := m.Read(composerPath)
	if err != nil {
		return nil, cli.WrapVerb(err, "read", composerJSONFile)
	}

	var raw map[string]json.RawMessage
	if err := json.Unmarshal([]byte(content), &raw); err != nil {
		return nil, cli.WrapVerb(err, "parse", composerJSONFile)
	}

	return raw, nil
}

// writeComposerJSON writes the composer.json to the given directory.
func writeComposerJSON(dir string, raw map[string]json.RawMessage) error {
	m := getMedium()
	composerPath := filepath.Join(dir, composerJSONFile)

	data, err := json.MarshalIndent(raw, "", "    ")
	if err != nil {
		return cli.WrapVerb(err, "marshal", composerJSONFile)
	}

	// Add trailing newline
	content := string(data) + "\n"

	if err := m.Write(composerPath, content); err != nil {
		return cli.WrapVerb(err, "write", composerJSONFile)
	}

	return nil
}

// getRepositories extracts repositories from raw composer.json.
func getRepositories(raw map[string]json.RawMessage) ([]composerRepository, error) {
	reposRaw, ok := raw["repositories"]
	if !ok {
		return []composerRepository{}, nil
	}

	var repos []composerRepository
	if err := json.Unmarshal(reposRaw, &repos); err != nil {
		return nil, cli.WrapVerb(err, "parse", "repositories")
	}

	return repos, nil
}

// setRepositories sets repositories in raw composer.json.
func setRepositories(raw map[string]json.RawMessage, repos []composerRepository) error {
	if len(repos) == 0 {
		delete(raw, "repositories")
		return nil
	}

	reposData, err := json.Marshal(repos)
	if err != nil {
		return cli.WrapVerb(err, "marshal", "repositories")
	}

	raw["repositories"] = reposData
	return nil
}

// getPackageInfo reads package name and version from a composer.json in the given path.
func getPackageInfo(packagePath string) (name, version string, err error) {
	m := getMedium()
	composerPath := filepath.Join(packagePath, composerJSONFile)
	content, err := m.Read(composerPath)
	if err != nil {
		return "", "", cli.WrapVerb(err, "read", "package composer.json")
	}

	var pkg struct {
		Name    string `json:"name"`
		Version string `json:"version"`
	}

	if err := json.Unmarshal([]byte(content), &pkg); err != nil {
		return "", "", cli.WrapVerb(err, "parse", "package composer.json")
	}

	if pkg.Name == "" {
		return "", "", cli.Err("package name not found in composer.json")
	}

	return pkg.Name, pkg.Version, nil
}

// LinkPackages adds path repositories to composer.json for local package development.
func LinkPackages(dir string, packages []string) error {
	if !IsPHPProject(dir) {
		return cli.Err(notPHPProjectComposerMessage)
	}

	raw, err := readComposerJSON(dir)
	if err != nil {
		return err
	}

	repos, err := getRepositories(raw)
	if err != nil {
		return err
	}

	for _, packagePath := range packages {
		absPath, pkgName, err := validateLinkPackage(packagePath)
		if err != nil {
			return err
		}

		if isPackageLinked(repos, absPath) {
			continue
		}

		repos = append(repos, pathComposerRepository(absPath))
		cli.Print("Linked: %s -> %s\n", pkgName, absPath)
	}

	if err := setRepositories(raw, repos); err != nil {
		return err
	}

	return writeComposerJSON(dir, raw)
}

func validateLinkPackage(packagePath string) (string, string, error) {
	absPath, err := filepath.Abs(packagePath)
	if err != nil {
		return "", "", cli.Err("failed to resolve path %s: %w", packagePath, err)
	}

	if !IsPHPProject(absPath) {
		return "", "", cli.Err("not a PHP package (missing composer.json): %s", absPath)
	}

	pkgName, _, err := getPackageInfo(absPath)
	if err != nil {
		return "", "", cli.Err("failed to get package info from %s: %w", absPath, err)
	}

	return absPath, pkgName, nil
}

func isPackageLinked(repos []composerRepository, absPath string) bool {
	for _, repo := range repos {
		if repo.Type == "path" && repo.URL == absPath {
			return true
		}
	}
	return false
}

func pathComposerRepository(absPath string) composerRepository {
	return composerRepository{
		Type: "path",
		URL:  absPath,
		Options: map[string]any{
			"symlink": true,
		},
	}
}

// UnlinkPackages removes path repositories from composer.json.
func UnlinkPackages(dir string, packages []string) error {
	if !IsPHPProject(dir) {
		return cli.Err(notPHPProjectComposerMessage)
	}

	raw, err := readComposerJSON(dir)
	if err != nil {
		return err
	}

	repos, err := getRepositories(raw)
	if err != nil {
		return err
	}

	// Build set of packages to unlink
	toUnlink := make(map[string]bool)
	for _, pkg := range packages {
		toUnlink[pkg] = true
	}

	// Filter out unlinked packages
	filtered := make([]composerRepository, 0, len(repos))
	for _, repo := range repos {
		if !shouldUnlinkRepository(repo, toUnlink) {
			filtered = append(filtered, repo)
		}
	}

	if err := setRepositories(raw, filtered); err != nil {
		return err
	}

	return writeComposerJSON(dir, raw)
}

func shouldUnlinkRepository(repo composerRepository, toUnlink map[string]bool) bool {
	if repo.Type != "path" {
		return false
	}

	shouldUnlink := false
	if IsPHPProject(repo.URL) {
		pkgName, _, err := getPackageInfo(repo.URL)
		if err == nil && toUnlink[pkgName] {
			shouldUnlink = true
			cli.Print("Unlinked: %s\n", pkgName)
		}
	}

	for pkg := range toUnlink {
		if repo.URL == pkg || filepath.Base(repo.URL) == pkg {
			shouldUnlink = true
			cli.Print("Unlinked: %s\n", repo.URL)
			break
		}
	}

	return shouldUnlink
}

// UpdatePackages runs composer update for specific packages.
func UpdatePackages(dir string, packages []string) error {
	if !IsPHPProject(dir) {
		return cli.Err(notPHPProjectComposerMessage)
	}

	args := []string{"update"}
	args = append(args, packages...)

	cmd := exec.Command("composer", args...)
	cmd.Dir = dir
	cmd.Stdout = os.Stdout
	cmd.Stderr = os.Stderr

	return cmd.Run()
}

// ListLinkedPackages returns all path repositories from composer.json.
func ListLinkedPackages(dir string) ([]LinkedPackage, error) {
	if !IsPHPProject(dir) {
		return nil, cli.Err(notPHPProjectComposerMessage)
	}

	raw, err := readComposerJSON(dir)
	if err != nil {
		return nil, err
	}

	repos, err := getRepositories(raw)
	if err != nil {
		return nil, err
	}

	linked := make([]LinkedPackage, 0)
	for _, repo := range repos {
		if repo.Type != "path" {
			continue
		}

		pkg := LinkedPackage{
			Path: repo.URL,
		}

		// Try to get package info
		if IsPHPProject(repo.URL) {
			name, version, err := getPackageInfo(repo.URL)
			if err == nil {
				pkg.Name = name
				pkg.Version = version
			}
		}

		if pkg.Name == "" {
			pkg.Name = filepath.Base(repo.URL)
		}

		linked = append(linked, pkg)
	}

	return linked, nil
}
