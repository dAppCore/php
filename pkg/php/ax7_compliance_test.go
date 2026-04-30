package php

import (
	"bytes"
	"context"
	"encoding/json"
	"errors"
	"io"
	"net/http"
	"net/http/httptest"
	"os"
	"path/filepath"
	"strings"
	"testing/fstest"
	"time"

	"dappco.re/go/cli/pkg/cli"
	coreio "dappco.re/go/io"
)

type ax7BridgeHandler struct{}

func (ax7BridgeHandler) HandleBridgeCall(method string, args json.RawMessage) (any, error) {
	return map[string]string{"method": method, "args": string(args)}, nil
}

type ax7FailingCloser struct{}

func (ax7FailingCloser) Read([]byte) (int, error) {
	return 0, io.EOF
}

func (ax7FailingCloser) Close() error {
	return errors.New("close failed")
}

type ax7Service struct {
	name    string
	status  ServiceStatus
	logs    io.ReadCloser
	logErr  error
	stopErr error
}

func (s *ax7Service) Name() string {
	return s.name
}

func (s *ax7Service) Start(ctx context.Context) error {
	return nil
}

func (s *ax7Service) Stop() error {
	return s.stopErr
}

func (s *ax7Service) Logs(follow bool) (io.ReadCloser, error) {
	if s.logErr != nil {
		return nil, s.logErr
	}
	return s.logs, nil
}

func (s *ax7Service) Status() ServiceStatus {
	return s.status
}

func ax7WriteFile(t *T, path string, content string) {
	t.Helper()
	RequireNoError(t, os.MkdirAll(filepath.Dir(path), 0o755))
	RequireNoError(t, os.WriteFile(path, []byte(content), 0o644))
}

func ax7Executable(t *T, binDir string, name string, body string) string {
	t.Helper()
	path := filepath.Join(binDir, name)
	script := "#!/bin/sh\n" + body
	RequireNoError(t, os.MkdirAll(filepath.Dir(path), 0o755))
	RequireNoError(t, os.WriteFile(path, []byte(script), 0o755))
	return path
}

func ax7BinPath(t *T) string {
	t.Helper()
	bin := t.TempDir()
	t.Setenv("PATH", bin+string(os.PathListSeparator)+os.Getenv("PATH"))
	return bin
}

func ax7TempFile(t *T) *os.File {
	t.Helper()
	file, err := os.CreateTemp(t.TempDir(), "out-*")
	RequireNoError(t, err)
	t.Cleanup(func() { _ = file.Close() })
	return file
}

func ax7PHPProject(t *T) string {
	t.Helper()
	dir := t.TempDir()
	ax7WriteFile(t, filepath.Join(dir, composerJSONFile), `{"name":"acme/demo","require":{"php":"^8.3"}}`)
	return dir
}

func ax7LaravelProject(t *T) string {
	t.Helper()
	dir := t.TempDir()
	ax7WriteFile(t, filepath.Join(dir, "artisan"), testPHPShebang)
	ax7WriteFile(t, filepath.Join(dir, composerJSONFile), `{"name":"Acme Demo","require":{"php":"^8.3","laravel/framework":"^11.0","laravel/octane":"^2.0"}}`)
	ax7WriteFile(t, filepath.Join(dir, ".env"), "APP_NAME=\"Acme Demo\"\nAPP_URL=https://demo.test:8443/path\n")
	return dir
}

func ax7CommandProject(t *T, command string) string {
	t.Helper()
	dir := ax7PHPProject(t)
	bin := filepath.Join(dir, "vendor", "bin")
	ax7Executable(t, bin, command, ax7ExitOKScript)
	return dir
}

func ax7LongRunningCommand(t *T, name string) {
	t.Helper()
	bin := ax7BinPath(t)
	ax7Executable(t, bin, name, ax7ExitOKScript)
}

func ax7RuntimeCleanup(t *T, appName string) {
	t.Helper()
	home := t.TempDir()
	t.Setenv("HOME", home)
	t.Setenv("XDG_DATA_HOME", filepath.Join(home, "xdg"))
	dataDir, err := resolveDataDir(appName)
	if err == nil {
		_ = os.RemoveAll(dataDir)
		t.Cleanup(func() { _ = os.RemoveAll(dataDir) })
	}
}

func ax7CoolifyServer(t *T, status int) *httptest.Server {
	t.Helper()
	return httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		if status >= 400 {
			http.Error(w, `{"message":"boom"}`, status)
			return
		}
		w.Header().Set("Content-Type", testContentTypeJSON)
		switch {
		case strings.HasSuffix(r.URL.Path, "/deploy"):
			w.WriteHeader(http.StatusAccepted)
			_, _ = w.Write([]byte(`{"id":"` + ax7DeployID + `","status":"queued","commit_sha":"abc","branch":"main"}`))
		case strings.HasSuffix(r.URL.Path, "/rollback"):
			_, _ = w.Write([]byte(`{"id":"rollback-1","status":"queued","branch":"main"}`))
		case strings.Contains(r.URL.Path, "/deployments/"+ax7DeployID):
			_, _ = w.Write([]byte(`{"id":"` + ax7DeployID + `","status":"finished","commit_sha":"abc","branch":"main"}`))
		case strings.HasSuffix(r.URL.Path, "/deployments"):
			_, _ = w.Write([]byte(`[{"id":"current","status":"finished"},{"id":"previous","status":"finished"}]`))
		case strings.Contains(r.URL.Path, "/applications/"):
			_, _ = w.Write([]byte(`{"id":"app-1","name":"Demo","fqdn":"https://demo.test","status":"running"}`))
		default:
			http.NotFound(w, r)
		}
	}))
}

func ax7CoolifyProject(t *T, url string) string {
	t.Helper()
	dir := t.TempDir()
	ax7WriteFile(t, filepath.Join(dir, ".env"), "COOLIFY_URL="+url+"\nCOOLIFY_TOKEN=tok\nCOOLIFY_APP_ID=app-1\nCOOLIFY_STAGING_APP_ID=stage-1\n")
	return dir
}

func ax7FakeDocker(t *T, psOutput string) {
	t.Helper()
	bin := ax7BinPath(t)
	ax7Executable(t, bin, "docker", `if [ "$1" = "ps" ]; then
printf '`+psOutput+`'
exit 0
fi
if [ "$1" = "build" ]; then exit 0; fi
if [ "$1" = "run" ]; then printf '1234567890abcdef1234567890abcdef'; exit 0; fi
if [ "$1" = "exec" ]; then exit 0; fi
exit 0
`)
}

func TestPHP_SetMedium_Good(t *T) {
	old := DefaultMedium
	t.Cleanup(func() { SetMedium(old) })
	SetMedium(coreio.Local)
	AssertEqual(t, coreio.Local, DefaultMedium)
}

func TestPHP_SetMedium_Bad(t *T) {
	old := DefaultMedium
	t.Cleanup(func() { SetMedium(old) })
	SetMedium(nil)
	AssertEqual(t, nil, DefaultMedium)
}

func TestPHP_SetMedium_Ugly(t *T) {
	old := DefaultMedium
	t.Cleanup(func() { SetMedium(old) })
	SetMedium(coreio.Local)
	SetMedium(coreio.Local)
	AssertEqual(t, coreio.Local, getMedium())
}

func TestPHP_AddCommands_Good(t *T) {
	root := &cli.Command{}
	AddCommands(root)
	AssertGreater(t, len(root.Commands()), 0)
}

func TestPHP_AddCommands_Bad(t *T) {
	root := &cli.Command{Use: "root"}
	AddCommands(root)
	AssertEqual(t, "root", root.Use)
}

func TestPHP_AddCommands_Ugly(t *T) {
	root := &cli.Command{}
	AddCommands(root)
	AssertEqual(t, "php", root.Commands()[0].Use)
}

func TestPHP_AddPHPCommands_Good(t *T) {
	root := &cli.Command{}
	AddPHPCommands(root)
	AssertEqual(t, "php", root.Commands()[0].Use)
}

func TestPHP_AddPHPCommands_Bad(t *T) {
	root := &cli.Command{}
	AddPHPCommands(root)
	AssertGreaterOrEqual(t, len(root.Commands()[0].Commands()), 1)
}

func TestPHP_AddPHPCommands_Ugly(t *T) {
	root := &cli.Command{}
	AddPHPCommands(root)
	AssertNotNil(t, root.Commands()[0].PersistentPreRunE)
}

func TestPHP_AddPHPRootCommands_Good(t *T) {
	root := &cli.Command{}
	AddPHPRootCommands(root)
	AssertGreater(t, len(root.Commands()), 0)
}

func TestPHP_AddPHPRootCommands_Bad(t *T) {
	root := &cli.Command{}
	AddPHPRootCommands(root)
	AssertNotNil(t, root.PersistentPreRunE)
}

func TestPHP_AddPHPRootCommands_Ugly(t *T) {
	root := &cli.Command{Use: "php"}
	AddPHPRootCommands(root)
	AssertEqual(t, "php", root.Use)
}

func TestPHP_DetectFormatter_Good(t *T) {
	dir := ax7PHPProject(t)
	ax7WriteFile(t, filepath.Join(dir, "pint.json"), "{}")
	formatter, ok := DetectFormatter(dir)
	AssertTrue(t, ok)
	AssertEqual(t, FormatterPint, formatter)
}

func TestPHP_DetectFormatter_Bad(t *T) {
	dir := t.TempDir()
	formatter, ok := DetectFormatter(dir)
	AssertFalse(t, ok)
	AssertEqual(t, FormatterType(""), formatter)
}

func TestPHP_DetectFormatter_Ugly(t *T) {
	dir := ax7PHPProject(t)
	ax7Executable(t, filepath.Join(dir, "vendor", "bin"), "pint", ax7ExitOKScript)
	formatter, ok := DetectFormatter(dir)
	AssertTrue(t, ok)
	AssertEqual(t, FormatterPint, formatter)
}

func TestPHP_DetectAnalyser_Good(t *T) {
	dir := ax7PHPProject(t)
	ax7WriteFile(t, filepath.Join(dir, ax7PHPStanFile), ax7YAMLParameters)
	analyser, ok := DetectAnalyser(dir)
	AssertTrue(t, ok)
	AssertEqual(t, AnalyserPHPStan, analyser)
}

func TestPHP_DetectAnalyser_Bad(t *T) {
	dir := t.TempDir()
	analyser, ok := DetectAnalyser(dir)
	AssertFalse(t, ok)
	AssertEqual(t, AnalyserType(""), analyser)
}

func TestPHP_DetectAnalyser_Ugly(t *T) {
	dir := ax7PHPProject(t)
	ax7WriteFile(t, filepath.Join(dir, "phpstan.neon.dist"), ax7YAMLParameters)
	ax7WriteFile(t, filepath.Join(dir, "vendor", "larastan", "larastan", "extension.neon"), "")
	analyser, ok := DetectAnalyser(dir)
	AssertTrue(t, ok)
	AssertEqual(t, AnalyserLarastan, analyser)
}

func TestPHP_DetectPsalm_Good(t *T) {
	dir := ax7PHPProject(t)
	ax7WriteFile(t, filepath.Join(dir, "psalm.xml"), "<psalm/>")
	psalm, ok := DetectPsalm(dir)
	AssertTrue(t, ok)
	AssertEqual(t, PsalmStandard, psalm)
}

func TestPHP_DetectPsalm_Bad(t *T) {
	dir := t.TempDir()
	psalm, ok := DetectPsalm(dir)
	AssertFalse(t, ok)
	AssertEqual(t, PsalmType(""), psalm)
}

func TestPHP_DetectPsalm_Ugly(t *T) {
	dir := ax7PHPProject(t)
	ax7Executable(t, filepath.Join(dir, "vendor", "bin"), "psalm", ax7ExitOKScript)
	psalm, ok := DetectPsalm(dir)
	AssertTrue(t, ok)
	AssertEqual(t, PsalmStandard, psalm)
}

func TestPHP_DetectRector_Good(t *T) {
	dir := ax7PHPProject(t)
	ax7WriteFile(t, filepath.Join(dir, "rector.php"), "<?php return [];\n")
	ok := DetectRector(dir)
	AssertTrue(t, ok)
}

func TestPHP_DetectRector_Bad(t *T) {
	dir := t.TempDir()
	ok := DetectRector(dir)
	AssertFalse(t, ok)
}

func TestPHP_DetectRector_Ugly(t *T) {
	dir := ax7PHPProject(t)
	ax7Executable(t, filepath.Join(dir, "vendor", "bin"), "rector", ax7ExitOKScript)
	ok := DetectRector(dir)
	AssertTrue(t, ok)
}

func TestPHP_DetectInfection_Good(t *T) {
	dir := ax7PHPProject(t)
	ax7WriteFile(t, filepath.Join(dir, "infection.json"), "{}")
	ok := DetectInfection(dir)
	AssertTrue(t, ok)
}

func TestPHP_DetectInfection_Bad(t *T) {
	dir := t.TempDir()
	ok := DetectInfection(dir)
	AssertFalse(t, ok)
}

func TestPHP_DetectInfection_Ugly(t *T) {
	dir := ax7PHPProject(t)
	ax7Executable(t, filepath.Join(dir, "vendor", "bin"), "infection", ax7ExitOKScript)
	ok := DetectInfection(dir)
	AssertTrue(t, ok)
}

func TestPHP_DetectTestRunner_Good(t *T) {
	dir := ax7PHPProject(t)
	ax7WriteFile(t, filepath.Join(dir, "tests", ax7PestFile), ax7PHPOpen)
	runner := DetectTestRunner(dir)
	AssertEqual(t, TestRunnerPest, runner)
}

func TestPHP_DetectTestRunner_Bad(t *T) {
	dir := t.TempDir()
	runner := DetectTestRunner(dir)
	AssertEqual(t, TestRunnerPHPUnit, runner)
}

func TestPHP_DetectTestRunner_Ugly(t *T) {
	dir := ax7PHPProject(t)
	ax7WriteFile(t, filepath.Join(dir, "tests", "Feature", "ExampleTest.php"), ax7PHPOpen)
	runner := DetectTestRunner(dir)
	AssertEqual(t, TestRunnerPHPUnit, runner)
}

func TestPHP_DetectPackageManager_Bad(t *T) {
	dir := t.TempDir()
	manager := DetectPackageManager(dir)
	AssertEqual(t, "npm", manager)
}

func TestPHP_DetectPackageManager_Ugly(t *T) {
	dir := t.TempDir()
	ax7WriteFile(t, filepath.Join(dir, "bun.lockb"), "")
	ax7WriteFile(t, filepath.Join(dir, "package-lock.json"), "{}")
	manager := DetectPackageManager(dir)
	AssertEqual(t, "bun", manager)
}

func TestPHP_DetectServices_Ugly(t *T) {
	dir := ax7LaravelProject(t)
	ax7WriteFile(t, filepath.Join(dir, "vite.config.js"), "export default {}\n")
	ax7WriteFile(t, filepath.Join(dir, "config", "horizon.php"), ax7PHPOpen)
	services := DetectServices(dir)
	AssertContains(t, services, ServiceVite)
	AssertContains(t, services, ServiceHorizon)
}

func TestPHP_IsLaravelProject_Ugly(t *T) {
	dir := t.TempDir()
	ax7WriteFile(t, filepath.Join(dir, "artisan"), testPHPShebang)
	ax7WriteFile(t, filepath.Join(dir, composerJSONFile), `{"require-dev":{"laravel/framework":"^11.0"}}`)
	AssertTrue(t, IsLaravelProject(dir))
}

func TestPHP_IsFrankenPHPProject_Ugly(t *T) {
	dir := t.TempDir()
	ax7WriteFile(t, filepath.Join(dir, composerJSONFile), `{"require":{"laravel/octane":"^2.0"}}`)
	ax7WriteFile(t, filepath.Join(dir, "config", testOctaneFile), "<?php return ['server' => 'swoole'];")
	AssertFalse(t, IsFrankenPHPProject(dir))
}

func TestPHP_IsPHPProject_Ugly(t *T) {
	dir := t.TempDir()
	ax7WriteFile(t, filepath.Join(dir, composerJSONFile), "{")
	AssertTrue(t, IsPHPProject(dir))
}

func TestPHP_GetLaravelAppName_Ugly(t *T) {
	dir := t.TempDir()
	ax7WriteFile(t, filepath.Join(dir, ".env"), "APP_NAME='Quoted Name'\n")
	got := GetLaravelAppName(dir)
	AssertEqual(t, "Quoted Name", got)
}

func TestPHP_GetLaravelAppURL_Ugly(t *T) {
	dir := t.TempDir()
	ax7WriteFile(t, filepath.Join(dir, ".env"), "APP_URL='https://demo.test/path'\n")
	got := GetLaravelAppURL(dir)
	AssertEqual(t, "https://demo.test/path", got)
}

func TestPHP_ExtractDomainFromURL_Bad(t *T) {
	got := ExtractDomainFromURL("")
	AssertEqual(t, "", got)
	AssertFalse(t, strings.Contains(got, ":"))
}

func TestPHP_Format_Good(t *T) {
	dir := ax7CommandProject(t, "pint")
	var out bytes.Buffer
	err := Format(context.Background(), FormatOptions{Dir: dir, Fix: true, Output: &out})
	AssertNoError(t, err)
}

func TestPHP_Format_Bad(t *T) {
	dir := t.TempDir()
	err := Format(context.Background(), FormatOptions{Dir: dir, Output: io.Discard})
	AssertError(t, err, "no formatter found")
}

func TestPHP_Format_Ugly(t *T) {
	dir := ax7CommandProject(t, "pint")
	var out bytes.Buffer
	err := Format(context.Background(), FormatOptions{Dir: dir, Diff: true, JSON: true, Paths: []string{"app"}, Output: &out})
	AssertNoError(t, err)
}

func TestPHP_Analyse_Good(t *T) {
	dir := ax7CommandProject(t, "phpstan")
	ax7WriteFile(t, filepath.Join(dir, ax7PHPStanFile), ax7YAMLParameters)
	err := Analyse(context.Background(), AnalyseOptions{Dir: dir, Level: 5, Output: io.Discard})
	AssertNoError(t, err)
}

func TestPHP_Analyse_Bad(t *T) {
	dir := t.TempDir()
	err := Analyse(context.Background(), AnalyseOptions{Dir: dir, Output: io.Discard})
	AssertError(t, err, "no static analyser found")
}

func TestPHP_Analyse_Ugly(t *T) {
	dir := ax7CommandProject(t, "phpstan")
	ax7WriteFile(t, filepath.Join(dir, ax7PHPStanFile), ax7YAMLParameters)
	err := Analyse(context.Background(), AnalyseOptions{Dir: dir, JSON: true, SARIF: true, Paths: []string{"app"}, Output: io.Discard})
	AssertNoError(t, err)
}

func TestPHP_RunPsalm_Good(t *T) {
	dir := ax7CommandProject(t, "psalm")
	err := RunPsalm(context.Background(), PsalmOptions{Dir: dir, Level: 3, Output: io.Discard})
	AssertNoError(t, err)
}

func TestPHP_RunPsalm_Bad(t *T) {
	ctx, cancel := context.WithCancel(context.Background())
	cancel()
	err := RunPsalm(ctx, PsalmOptions{Dir: t.TempDir(), Output: io.Discard})
	AssertError(t, err)
}

func TestPHP_RunPsalm_Ugly(t *T) {
	dir := ax7CommandProject(t, "psalm")
	err := RunPsalm(context.Background(), PsalmOptions{Dir: dir, Fix: true, Baseline: true, ShowInfo: true, SARIF: true, Output: io.Discard})
	AssertNoError(t, err)
}

func TestPHP_RunRector_Good(t *T) {
	dir := ax7CommandProject(t, "rector")
	err := RunRector(context.Background(), RectorOptions{Dir: dir, Output: io.Discard})
	AssertNoError(t, err)
}

func TestPHP_RunRector_Bad(t *T) {
	ctx, cancel := context.WithCancel(context.Background())
	cancel()
	err := RunRector(ctx, RectorOptions{Dir: t.TempDir(), Output: io.Discard})
	AssertError(t, err)
}

func TestPHP_RunRector_Ugly(t *T) {
	dir := ax7CommandProject(t, "rector")
	err := RunRector(context.Background(), RectorOptions{Dir: dir, Fix: true, Diff: true, ClearCache: true, Output: io.Discard})
	AssertNoError(t, err)
}

func TestPHP_RunInfection_Good(t *T) {
	dir := ax7CommandProject(t, "infection")
	err := RunInfection(context.Background(), InfectionOptions{Dir: dir, Output: io.Discard})
	AssertNoError(t, err)
}

func TestPHP_RunInfection_Bad(t *T) {
	ctx, cancel := context.WithCancel(context.Background())
	cancel()
	err := RunInfection(ctx, InfectionOptions{Dir: t.TempDir(), Output: io.Discard})
	AssertError(t, err)
}

func TestPHP_RunInfection_Ugly(t *T) {
	dir := ax7CommandProject(t, "infection")
	err := RunInfection(context.Background(), InfectionOptions{Dir: dir, MinMSI: 80, MinCoveredMSI: 85, Threads: 1, Filter: "app", OnlyCovered: true, Output: io.Discard})
	AssertNoError(t, err)
}

func TestPHP_RunTests_Good(t *T) {
	dir := ax7CommandProject(t, "phpunit")
	err := RunTests(context.Background(), TestOptions{Dir: dir, Output: io.Discard})
	AssertNoError(t, err)
}

func TestPHP_RunTests_Bad(t *T) {
	ctx, cancel := context.WithCancel(context.Background())
	cancel()
	err := RunTests(ctx, TestOptions{Dir: t.TempDir(), Output: io.Discard})
	AssertError(t, err)
}

func TestPHP_RunTests_Ugly(t *T) {
	dir := ax7CommandProject(t, "pest")
	ax7WriteFile(t, filepath.Join(dir, "tests", ax7PestFile), ax7PHPOpen)
	err := RunTests(context.Background(), TestOptions{Dir: dir, Parallel: true, Coverage: true, CoverageFormat: "clover", Groups: []string{"feature"}, JUnit: true, Output: io.Discard})
	AssertNoError(t, err)
}

func TestPHP_RunParallel_Good(t *T) {
	dir := ax7CommandProject(t, "phpunit")
	ax7Executable(t, filepath.Join(dir, "vendor", "bin"), "paratest", ax7ExitOKScript)
	err := RunParallel(context.Background(), TestOptions{Dir: dir, Output: io.Discard})
	AssertNoError(t, err)
}

func TestPHP_RunParallel_Bad(t *T) {
	ctx, cancel := context.WithCancel(context.Background())
	cancel()
	err := RunParallel(ctx, TestOptions{Dir: t.TempDir(), Output: io.Discard})
	AssertError(t, err)
}

func TestPHP_RunParallel_Ugly(t *T) {
	dir := ax7CommandProject(t, "pest")
	ax7WriteFile(t, filepath.Join(dir, "tests", ax7PestFile), ax7PHPOpen)
	err := RunParallel(context.Background(), TestOptions{Dir: dir, Coverage: true, Output: io.Discard})
	AssertNoError(t, err)
}

func TestPHP_RunAudit_Good(t *T) {
	bin := ax7BinPath(t)
	ax7Executable(t, bin, "composer", ax7AuditNoAdvisoriesScript)
	results, err := RunAudit(context.Background(), AuditOptions{Dir: t.TempDir(), Output: io.Discard})
	AssertNoError(t, err)
	AssertEqual(t, "composer", results[0].Tool)
}

func TestPHP_RunAudit_Bad(t *T) {
	t.Setenv("PATH", t.TempDir())
	results, err := RunAudit(context.Background(), AuditOptions{Dir: t.TempDir(), Output: io.Discard})
	AssertNoError(t, err)
	AssertError(t, results[0].Error)
}

func TestPHP_RunAudit_Ugly(t *T) {
	bin := ax7BinPath(t)
	ax7Executable(t, bin, "composer", "printf '{\"advisories\":{\"pkg\":[{\"title\":\"bug\",\"link\":\"https://example.test\",\"cve\":\"CVE-1\"}]}}'\n")
	results, err := RunAudit(context.Background(), AuditOptions{Dir: t.TempDir(), JSON: true, Output: io.Discard})
	AssertNoError(t, err)
	AssertEqual(t, 1, results[0].Vulnerabilities)
}

func TestPHP_RunSecurityChecks_Good(t *T) {
	bin := ax7BinPath(t)
	ax7Executable(t, bin, "composer", ax7AuditNoAdvisoriesScript)
	result, err := RunSecurityChecks(context.Background(), SecurityOptions{Dir: t.TempDir(), Output: io.Discard})
	AssertNoError(t, err)
	AssertGreater(t, result.Summary.Total, 0)
}

func TestPHP_RunSecurityChecks_Bad(t *T) {
	bin := ax7BinPath(t)
	ax7Executable(t, bin, "composer", ax7AuditNoAdvisoriesScript)
	dir := t.TempDir()
	ax7WriteFile(t, filepath.Join(dir, ".env"), "APP_DEBUG=true\n")
	result, err := RunSecurityChecks(context.Background(), SecurityOptions{Dir: dir, Output: io.Discard})
	AssertNoError(t, err)
	AssertGreater(t, result.Summary.Critical, 0)
}

func TestPHP_RunSecurityChecks_Ugly(t *T) {
	bin := ax7BinPath(t)
	ax7Executable(t, bin, "composer", ax7AuditNoAdvisoriesScript)
	dir := t.TempDir()
	ax7WriteFile(t, filepath.Join(dir, ".env"), "APP_KEY=\n")
	result, err := RunSecurityChecks(context.Background(), SecurityOptions{Dir: dir, JSON: true, SARIF: true, Output: io.Discard})
	AssertNoError(t, err)
	AssertGreater(t, result.Summary.Total, 0)
}

func TestPHP_GetQAStages_Good(t *T) {
	stages := GetQAStages(QAOptions{})
	AssertLen(t, stages, 2)
	AssertEqual(t, QAStageQuick, stages[0])
}

func TestPHP_GetQAStages_Bad(t *T) {
	stages := GetQAStages(QAOptions{Quick: true, Full: true})
	AssertLen(t, stages, 1)
	AssertEqual(t, QAStageQuick, stages[0])
}

func TestPHP_GetQAStages_Ugly(t *T) {
	stages := GetQAStages(QAOptions{Full: true})
	AssertContains(t, stages, QAStageFull)
	AssertLen(t, stages, 3)
}

func TestPHP_GetQAChecks_Good(t *T) {
	checks := GetQAChecks(t.TempDir(), QAStageQuick)
	AssertContains(t, checks, "audit")
	AssertContains(t, checks, "fmt")
}

func TestPHP_GetQAChecks_Bad(t *T) {
	checks := GetQAChecks(t.TempDir(), QAStage("missing"))
	AssertEqual(t, []string(nil), checks)
	AssertLen(t, checks, 0)
}

func TestPHP_GetQAChecks_Ugly(t *T) {
	dir := ax7PHPProject(t)
	ax7WriteFile(t, filepath.Join(dir, "rector.php"), ax7PHPOpen)
	ax7WriteFile(t, filepath.Join(dir, "infection.json"), "{}")
	checks := GetQAChecks(dir, QAStageFull)
	AssertContains(t, checks, "rector")
	AssertContains(t, checks, "infection")
}

func TestPHP_GenerateDockerfile_Ugly(t *T) {
	dir := ax7PHPProject(t)
	ax7WriteFile(t, filepath.Join(dir, packageJSONFile), `{"scripts":{"build":"vite build"}}`)
	got, err := GenerateDockerfile(dir)
	AssertNoError(t, err)
	AssertContains(t, got, "FROM node:20-alpine")
}

func TestPHP_DetectDockerfileConfig_Ugly(t *T) {
	dir := ax7PHPProject(t)
	ax7WriteFile(t, filepath.Join(dir, composerJSONFile), `{"require":{"php":"8"}}`)
	config, err := DetectDockerfileConfig(dir)
	AssertNoError(t, err)
	AssertEqual(t, "8.0", config.PHPVersion)
}

func TestPHP_GenerateDockerfileFromConfig_Bad(t *T) {
	defer func() { AssertNotNil(t, recover()) }()
	var config *DockerfileConfig
	_ = GenerateDockerfileFromConfig(config)
	AssertTrue(t, false, "nil config should panic")
}

func TestPHP_GenerateDockerfileFromConfig_Ugly(t *T) {
	config := &DockerfileConfig{PHPVersion: "8.4", BaseImage: "example/php", HasAssets: true, PackageManager: "bun"}
	got := GenerateDockerfileFromConfig(config)
	AssertContains(t, got, "bun install")
	AssertContains(t, got, "example/php:latest-php8.4")
}

func TestPHP_GenerateDockerignore_Bad(t *T) {
	got := GenerateDockerignore("")
	AssertContains(t, got, ".env")
	AssertNotContains(t, got, "\x00")
}

func TestPHP_GenerateDockerignore_Ugly(t *T) {
	got := GenerateDockerignore(t.TempDir())
	AssertContains(t, got, "storage/framework/cache/*")
	AssertContains(t, got, "Dockerfile*")
}

func TestPHP_BuildDocker_Good(t *T) {
	ax7FakeDocker(t, "")
	dir := ax7PHPProject(t)
	err := BuildDocker(context.Background(), DockerBuildOptions{ProjectDir: dir, ImageName: "demo", Dockerfile: filepath.Join(dir, composerJSONFile), Output: io.Discard})
	AssertNoError(t, err)
}

func TestPHP_BuildDocker_Ugly(t *T) {
	ax7FakeDocker(t, "")
	dir := ax7LaravelProject(t)
	err := BuildDocker(context.Background(), DockerBuildOptions{ProjectDir: dir, Platform: "linux/amd64", NoBuildCache: true, BuildArgs: map[string]string{"APP_ENV": "test"}, Output: io.Discard})
	AssertNoError(t, err)
}

func TestPHP_BuildLinuxKit_Good(t *T) {
	bin := ax7BinPath(t)
	ax7Executable(t, bin, "linuxkit", ax7ExitOKScript)
	dir := ax7PHPProject(t)
	err := BuildLinuxKit(context.Background(), LinuxKitBuildOptions{ProjectDir: dir, Output: io.Discard})
	AssertNoError(t, err)
}

func TestPHP_BuildLinuxKit_Ugly(t *T) {
	bin := ax7BinPath(t)
	ax7Executable(t, bin, "linuxkit", ax7ExitOKScript)
	dir := ax7PHPProject(t)
	err := BuildLinuxKit(context.Background(), LinuxKitBuildOptions{ProjectDir: dir, Template: defaultLinuxKitTemplateName, Format: "iso", Variables: map[string]string{"EXTRA": "1"}, Output: io.Discard})
	AssertNoError(t, err)
}

func TestPHP_ServeProduction_Good(t *T) {
	ax7FakeDocker(t, "")
	err := ServeProduction(context.Background(), ServeOptions{ImageName: "demo", Detach: true, Output: io.Discard})
	AssertNoError(t, err)
}

func TestPHP_ServeProduction_Ugly(t *T) {
	ax7FakeDocker(t, "")
	err := ServeProduction(context.Background(), ServeOptions{ImageName: "demo", Tag: "edge", Port: 8080, HTTPSPort: 8443, EnvFile: ".env", Volumes: map[string]string{"/tmp": "/data"}, Output: io.Discard})
	AssertNoError(t, err)
}

func TestPHP_Shell_Good(t *T) {
	ax7FakeDocker(t, "abcdef1234567890\n")
	err := Shell(context.Background(), "abcdef")
	AssertNoError(t, err)
}

func TestPHP_Shell_Ugly(t *T) {
	ax7FakeDocker(t, "abcdef1234567890\nabcdef9999999999\n")
	err := Shell(context.Background(), "abcdef")
	AssertError(t, err, "multiple containers")
}

func TestPHP_Extract_Good(t *T) {
	fsys := fstest.MapFS{"laravel/artisan": {Data: []byte("php")}}
	dir, err := Extract(fsys, "laravel")
	t.Cleanup(func() { _ = os.RemoveAll(dir) })
	AssertNoError(t, err)
	_, statErr := os.Stat(filepath.Join(dir, "artisan"))
	AssertNoError(t, statErr)
}

func TestPHP_Extract_Bad(t *T) {
	fsys := fstest.MapFS{"other/file.txt": {Data: []byte("x")}}
	dir, err := Extract(fsys, "laravel")
	AssertError(t, err)
	AssertEqual(t, "", dir)
}

func TestPHP_Extract_Ugly(t *T) {
	fsys := fstest.MapFS{"laravel/nested/file.txt": {Data: []byte("x")}}
	dir, err := Extract(fsys, "laravel")
	t.Cleanup(func() { _ = os.RemoveAll(dir) })
	AssertNoError(t, err)
	AssertTrue(t, filepath.IsAbs(dir))
}

func TestPHP_GetSSLDir_Ugly(t *T) {
	dir := filepath.Join(t.TempDir(), "nested", "ssl")
	got, err := GetSSLDir(SSLOptions{Dir: dir})
	AssertNoError(t, err)
	AssertEqual(t, dir, got)
}

func TestPHP_CertPaths_Ugly(t *T) {
	dir := t.TempDir()
	cert, key, err := CertPaths("a.b.test", SSLOptions{Dir: dir})
	AssertNoError(t, err)
	AssertContains(t, cert, "a.b.test.pem")
	AssertContains(t, key, "a.b.test-key.pem")
}

func TestPHP_CertsExist_Ugly(t *T) {
	dir := t.TempDir()
	ax7WriteFile(t, filepath.Join(dir, "missing-key.test.pem"), "cert")
	ok := CertsExist("missing-key.test", SSLOptions{Dir: dir})
	AssertFalse(t, ok)
}

func TestPHP_SetupSSL_Good(t *T) {
	bin := ax7BinPath(t)
	ax7Executable(t, bin, "mkcert", `if [ "$1" = "-install" ]; then exit 0; fi
while [ "$#" -gt 0 ]; do
if [ "$1" = "-cert-file" ]; then shift; cert="$1"; fi
if [ "$1" = "-key-file" ]; then shift; key="$1"; fi
shift
done
printf cert > "$cert"
printf key > "$key"
`)
	dir := t.TempDir()
	err := SetupSSL(ax7DemoDomain, SSLOptions{Dir: dir})
	AssertNoError(t, err)
	AssertTrue(t, CertsExist(ax7DemoDomain, SSLOptions{Dir: dir}))
}

func TestPHP_SetupSSL_Ugly(t *T) {
	bin := ax7BinPath(t)
	ax7Executable(t, bin, "mkcert", "if [ \"$1\" = \"-install\" ]; then exit 0; fi\nexit 2\n")
	err := SetupSSL(ax7DemoDomain, SSLOptions{Dir: t.TempDir()})
	AssertError(t, err, "failed to generate certificates")
}

func TestPHP_SetupSSLIfNeeded_Ugly(t *T) {
	dir := t.TempDir()
	ax7WriteFile(t, filepath.Join(dir, "demo.test.pem"), "cert")
	_, _, err := SetupSSLIfNeeded(ax7DemoDomain, SSLOptions{Dir: dir})
	AssertError(t, err)
}

func TestPHP_IsMkcertInstalled_Bad(t *T) {
	t.Setenv("PATH", t.TempDir())
	got := IsMkcertInstalled()
	AssertFalse(t, got)
}

func TestPHP_IsMkcertInstalled_Ugly(t *T) {
	bin := ax7BinPath(t)
	ax7Executable(t, bin, "mkcert", ax7ExitOKScript)
	got := IsMkcertInstalled()
	AssertTrue(t, got)
}

func TestPHP_InstallMkcertCA_Good(t *T) {
	bin := ax7BinPath(t)
	ax7Executable(t, bin, "mkcert", ax7ExitOKScript)
	err := InstallMkcertCA()
	AssertNoError(t, err)
}

func TestPHP_InstallMkcertCA_Ugly(t *T) {
	bin := ax7BinPath(t)
	ax7Executable(t, bin, "mkcert", "exit 3\n")
	err := InstallMkcertCA()
	AssertError(t, err, "failed to install")
}

func TestPHP_GetMkcertCARoot_Good(t *T) {
	bin := ax7BinPath(t)
	ax7Executable(t, bin, "mkcert", "if [ \"$1\" = \"-CAROOT\" ]; then printf '/tmp/core-ca'; exit 0; fi\nexit 0\n")
	root, err := GetMkcertCARoot()
	AssertNoError(t, err)
	AssertEqual(t, "/tmp/core-ca", root)
}

func TestPHP_GetMkcertCARoot_Ugly(t *T) {
	bin := ax7BinPath(t)
	ax7Executable(t, bin, "mkcert", "if [ \"$1\" = \"-CAROOT\" ]; then exit 4; fi\nexit 0\n")
	root, err := GetMkcertCARoot()
	AssertError(t, err)
	AssertEqual(t, "", root)
}

func TestPHP_PrepareRuntimeEnvironment_Good(t *T) {
	appName := "core-php-ax7-good"
	ax7RuntimeCleanup(t, appName)
	root := t.TempDir()
	ax7WriteFile(t, filepath.Join(root, "storage", ".gitkeep"), "")
	env, err := PrepareRuntimeEnvironment(root, appName)
	AssertNoError(t, err)
	AssertTrue(t, strings.HasSuffix(env.DatabasePath, appName+".sqlite"))
}

func TestPHP_PrepareRuntimeEnvironment_Bad(t *T) {
	appName := "core-php-ax7-bad"
	ax7RuntimeCleanup(t, appName)
	env, err := PrepareRuntimeEnvironment(filepath.Join(t.TempDir(), "missing"), appName)
	AssertError(t, err)
	AssertEqual(t, (*RuntimeEnvironment)(nil), env)
}

func TestPHP_PrepareRuntimeEnvironment_Ugly(t *T) {
	appName := "core-php-ax7-ugly"
	ax7RuntimeCleanup(t, appName)
	root := t.TempDir()
	ax7WriteFile(t, filepath.Join(root, "storage", ".gitkeep"), "")
	first, err := PrepareRuntimeEnvironment(root, appName)
	AssertNoError(t, err)
	AssertTrue(t, filepath.IsAbs(first.DataDir))
}

func TestPHP_AppendEnv_Good(t *T) {
	root := t.TempDir()
	ax7WriteFile(t, filepath.Join(root, ".env"), "APP_NAME=Demo\n")
	err := AppendEnv(root, "NATIVE_BRIDGE_URL", "http://127.0.0.1:1")
	AssertNoError(t, err)
}

func TestPHP_AppendEnv_Bad(t *T) {
	root := t.TempDir()
	err := AppendEnv(root, "MISSING", "value")
	AssertError(t, err)
}

func TestPHP_AppendEnv_Ugly(t *T) {
	root := t.TempDir()
	ax7WriteFile(t, filepath.Join(root, ".env"), "")
	err := AppendEnv(root, "SPACED", "value with spaces")
	AssertNoError(t, err)
}

func TestPHP_NewCoolifyClient_Bad(t *T) {
	client := NewCoolifyClient("https://coolify.test/", "")
	AssertEqual(t, "https://coolify.test", client.BaseURL)
	AssertEqual(t, "", client.Token)
}

func TestPHP_NewCoolifyClient_Ugly(t *T) {
	client := NewCoolifyClient("http://127.0.0.1:8000///", "tok")
	AssertEqual(t, "http://127.0.0.1:8000", client.BaseURL)
	AssertNotNil(t, client.HTTPClient)
}

func TestPHP_LoadCoolifyConfig_Ugly(t *T) {
	dir := t.TempDir()
	t.Setenv("COOLIFY_URL", "https://env.test")
	t.Setenv("COOLIFY_TOKEN", "env-token")
	config, err := LoadCoolifyConfig(dir)
	AssertNoError(t, err)
	AssertEqual(t, "https://env.test", config.URL)
}

func TestPHP_LoadCoolifyConfigFromFile_Ugly(t *T) {
	path := filepath.Join(t.TempDir(), ".env")
	ax7WriteFile(t, path, "COOLIFY_URL='https://file.test'\nCOOLIFY_TOKEN=\"tok\"\n")
	config, err := LoadCoolifyConfigFromFile(path)
	AssertNoError(t, err)
	AssertEqual(t, "https://file.test", config.URL)
}

func TestPHP_CoolifyClient_TriggerDeploy_Ugly(t *T) {
	server := ax7CoolifyServer(t, http.StatusAccepted)
	defer server.Close()
	deployment, err := NewCoolifyClient(server.URL, "tok").TriggerDeploy(context.Background(), "app-1", true)
	AssertNoError(t, err)
	AssertEqual(t, ax7DeployID, deployment.ID)
}

func TestPHP_CoolifyClient_GetDeployment_Ugly(t *T) {
	server := ax7CoolifyServer(t, http.StatusOK)
	defer server.Close()
	deployment, err := NewCoolifyClient(server.URL, "tok").GetDeployment(context.Background(), "app-1", ax7DeployID)
	AssertNoError(t, err)
	AssertEqual(t, "finished", deployment.Status)
}

func TestPHP_CoolifyClient_ListDeployments_Bad(t *T) {
	server := ax7CoolifyServer(t, http.StatusInternalServerError)
	defer server.Close()
	deployments, err := NewCoolifyClient(server.URL, "tok").ListDeployments(context.Background(), "app-1", 1)
	AssertError(t, err)
	AssertEqual(t, []CoolifyDeployment(nil), deployments)
}

func TestPHP_CoolifyClient_ListDeployments_Ugly(t *T) {
	server := ax7CoolifyServer(t, http.StatusOK)
	defer server.Close()
	deployments, err := NewCoolifyClient(server.URL, "tok").ListDeployments(context.Background(), "app-1", 0)
	AssertNoError(t, err)
	AssertLen(t, deployments, 2)
}

func TestPHP_CoolifyClient_Rollback_Bad(t *T) {
	server := ax7CoolifyServer(t, http.StatusBadRequest)
	defer server.Close()
	deployment, err := NewCoolifyClient(server.URL, "tok").Rollback(context.Background(), "app-1", "bad")
	AssertError(t, err)
	AssertEqual(t, (*CoolifyDeployment)(nil), deployment)
}

func TestPHP_CoolifyClient_Rollback_Ugly(t *T) {
	server := ax7CoolifyServer(t, http.StatusOK)
	defer server.Close()
	deployment, err := NewCoolifyClient(server.URL, "tok").Rollback(context.Background(), "app-1", "previous")
	AssertNoError(t, err)
	AssertEqual(t, "rollback-1", deployment.ID)
}

func TestPHP_CoolifyClient_GetApp_Bad(t *T) {
	server := ax7CoolifyServer(t, http.StatusNotFound)
	defer server.Close()
	app, err := NewCoolifyClient(server.URL, "tok").GetApp(context.Background(), "missing")
	AssertError(t, err)
	AssertEqual(t, (*CoolifyApp)(nil), app)
}

func TestPHP_CoolifyClient_GetApp_Ugly(t *T) {
	server := ax7CoolifyServer(t, http.StatusOK)
	defer server.Close()
	app, err := NewCoolifyClient(server.URL, "tok").GetApp(context.Background(), "app-1")
	AssertNoError(t, err)
	AssertEqual(t, "https://demo.test", app.FQDN)
}

func TestPHP_Deploy_Good(t *T) {
	server := ax7CoolifyServer(t, http.StatusOK)
	defer server.Close()
	status, err := Deploy(context.Background(), DeployOptions{Dir: ax7CoolifyProject(t, server.URL)})
	AssertNoError(t, err)
	AssertEqual(t, ax7DeployID, status.ID)
}

func TestPHP_Deploy_Bad(t *T) {
	status, err := Deploy(context.Background(), DeployOptions{Dir: t.TempDir()})
	AssertError(t, err)
	AssertEqual(t, (*DeploymentStatus)(nil), status)
}

func TestPHP_Deploy_Ugly(t *T) {
	server := ax7CoolifyServer(t, http.StatusOK)
	defer server.Close()
	status, err := Deploy(context.Background(), DeployOptions{Dir: ax7CoolifyProject(t, server.URL), Environment: EnvStaging, Force: true, Wait: true, PollInterval: time.Millisecond})
	AssertNoError(t, err)
	AssertEqual(t, "https://demo.test", status.URL)
}

func TestPHP_DeployStatus_Good(t *T) {
	server := ax7CoolifyServer(t, http.StatusOK)
	defer server.Close()
	status, err := DeployStatus(context.Background(), StatusOptions{Dir: ax7CoolifyProject(t, server.URL), DeploymentID: ax7DeployID})
	AssertNoError(t, err)
	AssertEqual(t, "finished", status.Status)
}

func TestPHP_DeployStatus_Bad(t *T) {
	status, err := DeployStatus(context.Background(), StatusOptions{Dir: t.TempDir()})
	AssertError(t, err)
	AssertEqual(t, (*DeploymentStatus)(nil), status)
}

func TestPHP_DeployStatus_Ugly(t *T) {
	server := ax7CoolifyServer(t, http.StatusOK)
	defer server.Close()
	status, err := DeployStatus(context.Background(), StatusOptions{Dir: ax7CoolifyProject(t, server.URL)})
	AssertNoError(t, err)
	AssertEqual(t, "current", status.ID)
}

func TestPHP_Rollback_Bad(t *T) {
	status, err := Rollback(context.Background(), RollbackOptions{Dir: t.TempDir()})
	AssertError(t, err)
	AssertEqual(t, (*DeploymentStatus)(nil), status)
}

func TestPHP_Rollback_Ugly(t *T) {
	server := ax7CoolifyServer(t, http.StatusOK)
	defer server.Close()
	status, err := Rollback(context.Background(), RollbackOptions{Dir: ax7CoolifyProject(t, server.URL), DeploymentID: "previous"})
	AssertNoError(t, err)
	AssertEqual(t, "rollback-1", status.ID)
}

func TestPHP_ListDeployments_Bad(t *T) {
	deployments, err := ListDeployments(context.Background(), t.TempDir(), EnvProduction, 1)
	AssertError(t, err)
	AssertEqual(t, []DeploymentStatus(nil), deployments)
}

func TestPHP_ListDeployments_Ugly(t *T) {
	server := ax7CoolifyServer(t, http.StatusOK)
	defer server.Close()
	deployments, err := ListDeployments(context.Background(), ax7CoolifyProject(t, server.URL), EnvStaging, 0)
	AssertNoError(t, err)
	AssertLen(t, deployments, 2)
}

func TestPHP_IsDeploymentComplete_Bad(t *T) {
	status := "deploying"
	got := IsDeploymentComplete(status)
	AssertFalse(t, got)
}

func TestPHP_IsDeploymentSuccessful_Bad(t *T) {
	status := "failed"
	got := IsDeploymentSuccessful(status)
	AssertFalse(t, got)
}

func TestPHP_NewBridge_Good(t *T) {
	bridge, err := NewBridge(ax7BridgeHandler{})
	RequireNoError(t, err)
	t.Cleanup(func() { _ = bridge.Shutdown(context.Background()) })
	AssertGreater(t, bridge.Port(), 0)
}

func TestPHP_NewBridge_Bad(t *T) {
	bridge, err := NewBridge(nil)
	RequireNoError(t, err)
	t.Cleanup(func() { _ = bridge.Shutdown(context.Background()) })
	AssertNotNil(t, bridge)
}

func TestPHP_NewBridge_Ugly(t *T) {
	bridge, err := NewBridge(ax7BridgeHandler{})
	RequireNoError(t, err)
	t.Cleanup(func() { _ = bridge.Shutdown(context.Background()) })
	resp, err := http.Get(bridge.URL() + "/bridge/health")
	AssertNoError(t, err)
	AssertEqual(t, http.StatusOK, resp.StatusCode)
}

func TestPHP_Bridge_Port_Good(t *T) {
	bridge, err := NewBridge(ax7BridgeHandler{})
	RequireNoError(t, err)
	t.Cleanup(func() { _ = bridge.Shutdown(context.Background()) })
	port := bridge.Port()
	AssertGreater(t, port, 0)
}

func TestPHP_Bridge_Port_Bad(t *T) {
	bridge := &Bridge{}
	port := bridge.Port()
	AssertEqual(t, 0, port)
}

func TestPHP_Bridge_Port_Ugly(t *T) {
	bridge := &Bridge{port: -1}
	port := bridge.Port()
	AssertEqual(t, -1, port)
}

func TestPHP_Bridge_URL_Good(t *T) {
	bridge := &Bridge{port: 1234}
	got := bridge.URL()
	AssertEqual(t, "http://127.0.0.1:1234", got)
}

func TestPHP_Bridge_URL_Bad(t *T) {
	bridge := &Bridge{}
	got := bridge.URL()
	AssertContains(t, got, ":0")
}

func TestPHP_Bridge_URL_Ugly(t *T) {
	bridge := &Bridge{port: 65535}
	got := bridge.URL()
	AssertEqual(t, "http://127.0.0.1:65535", got)
}

func TestPHP_Bridge_Shutdown_Good(t *T) {
	bridge, err := NewBridge(ax7BridgeHandler{})
	RequireNoError(t, err)
	err = bridge.Shutdown(context.Background())
	AssertNoError(t, err)
}

func TestPHP_Bridge_Shutdown_Bad(t *T) {
	bridge, err := NewBridge(ax7BridgeHandler{})
	RequireNoError(t, err)
	ctx, cancel := context.WithCancel(context.Background())
	cancel()
	err = bridge.Shutdown(ctx)
	AssertNoError(t, err)
	AssertGreater(t, bridge.Port(), 0)
}

func TestPHP_Bridge_Shutdown_Ugly(t *T) {
	bridge, err := NewBridge(ax7BridgeHandler{})
	RequireNoError(t, err)
	_ = bridge.Shutdown(context.Background())
	err = bridge.Shutdown(context.Background())
	AssertNoError(t, err)
}

func TestPHP_NewHandler_Good(t *T) {
	root := t.TempDir()
	handler, cleanup, err := NewHandler(root, HandlerConfig{})
	t.Cleanup(cleanup)
	AssertError(t, err, "not built")
	AssertEqual(t, filepath.Join(root, "public"), handler.DocRoot())
}

func TestPHP_NewHandler_Bad(t *T) {
	handler, cleanup, err := NewHandler("", HandlerConfig{NumThreads: 1, NumWorkers: 1})
	t.Cleanup(cleanup)
	AssertError(t, err)
	AssertEqual(t, "public", handler.DocRoot())
}

func TestPHP_NewHandler_Ugly(t *T) {
	root := filepath.Join(t.TempDir(), "space path")
	handler, cleanup, err := NewHandler(root, HandlerConfig{PHPIni: map[string]string{"x": "y"}})
	t.Cleanup(cleanup)
	AssertError(t, err)
	AssertEqual(t, root, handler.LaravelRoot())
}

func TestPHP_Handler_LaravelRoot_Good(t *T) {
	handler := &Handler{laravelRoot: "/app", docRoot: ax7PublicPath}
	got := handler.LaravelRoot()
	AssertEqual(t, "/app", got)
}

func TestPHP_Handler_LaravelRoot_Bad(t *T) {
	handler := &Handler{}
	got := handler.LaravelRoot()
	AssertEqual(t, "", got)
}

func TestPHP_Handler_LaravelRoot_Ugly(t *T) {
	handler := &Handler{laravelRoot: "/tmp/a b"}
	got := handler.LaravelRoot()
	AssertContains(t, got, "a b")
}

func TestPHP_Handler_DocRoot_Good(t *T) {
	handler := &Handler{laravelRoot: "/app", docRoot: ax7PublicPath}
	got := handler.DocRoot()
	AssertEqual(t, ax7PublicPath, got)
}

func TestPHP_Handler_DocRoot_Bad(t *T) {
	handler := &Handler{}
	got := handler.DocRoot()
	AssertEqual(t, "", got)
}

func TestPHP_Handler_DocRoot_Ugly(t *T) {
	handler := &Handler{docRoot: filepath.Join("relative", "public")}
	got := handler.DocRoot()
	AssertContains(t, got, "public")
}

func TestPHP_Handler_ServeHTTP_Good(t *T) {
	handler := &Handler{}
	rec := httptest.NewRecorder()
	handler.ServeHTTP(rec, httptest.NewRequest(http.MethodGet, "/", nil))
	AssertEqual(t, http.StatusNotImplemented, rec.Code)
}

func TestPHP_Handler_ServeHTTP_Bad(t *T) {
	handler := &Handler{}
	rec := httptest.NewRecorder()
	handler.ServeHTTP(rec, httptest.NewRequest(http.MethodPost, "/missing.php", nil))
	AssertContains(t, rec.Body.String(), "not built")
}

func TestPHP_Handler_ServeHTTP_Ugly(t *T) {
	handler := &Handler{docRoot: t.TempDir()}
	rec := httptest.NewRecorder()
	handler.ServeHTTP(rec, httptest.NewRequest(http.MethodGet, "/assets/app.css?x=1", nil))
	AssertEqual(t, http.StatusNotImplemented, rec.Code)
}

func TestPHP_ResponseWriter_Header_Good(t *T) {
	out := ax7TempFile(t)
	writer := &execResponseWriter{out: out}
	header := writer.Header()
	AssertNotNil(t, header)
}

func TestPHP_ResponseWriter_Header_Bad(t *T) {
	writer := &execResponseWriter{out: nil}
	header := writer.Header()
	AssertLen(t, header, 0)
}

func TestPHP_ResponseWriter_Header_Ugly(t *T) {
	out := ax7TempFile(t)
	writer := &execResponseWriter{out: out}
	header := writer.Header()
	AssertEqual(t, http.Header{}, header)
}

func TestPHP_ResponseWriter_Write_Good(t *T) {
	out := ax7TempFile(t)
	writer := &execResponseWriter{out: out}
	n, err := writer.Write([]byte("hello"))
	AssertNoError(t, err)
	AssertEqual(t, 5, n)
}

func TestPHP_ResponseWriter_Write_Bad(t *T) {
	out := ax7TempFile(t)
	RequireNoError(t, out.Close())
	writer := &execResponseWriter{out: out}
	n, err := writer.Write([]byte("hello"))
	AssertError(t, err)
	AssertEqual(t, 0, n)
}

func TestPHP_ResponseWriter_Write_Ugly(t *T) {
	out := ax7TempFile(t)
	writer := &execResponseWriter{out: out}
	n, err := writer.Write(nil)
	AssertNoError(t, err)
	AssertEqual(t, 0, n)
}

func TestPHP_ResponseWriter_WriteHeader_Good(t *T) {
	out := ax7TempFile(t)
	writer := &execResponseWriter{out: out}
	writer.WriteHeader(http.StatusCreated)
	AssertNotNil(t, writer)
}

func TestPHP_ResponseWriter_WriteHeader_Bad(t *T) {
	writer := &execResponseWriter{out: nil}
	writer.WriteHeader(http.StatusInternalServerError)
	AssertEqual(t, (*os.File)(nil), writer.out)
}

func TestPHP_ResponseWriter_WriteHeader_Ugly(t *T) {
	out := ax7TempFile(t)
	writer := &execResponseWriter{out: out}
	writer.WriteHeader(0)
	AssertEqual(t, out, writer.out)
}

func TestPHP_NewFrankenPHPService_Bad(t *T) {
	service := NewFrankenPHPService("", FrankenPHPOptions{})
	AssertEqual(t, "FrankenPHP", service.Name())
	AssertEqual(t, 8000, service.Status().Port)
}

func TestPHP_NewFrankenPHPService_Ugly(t *T) {
	service := NewFrankenPHPService("/app", FrankenPHPOptions{Port: 9000, HTTPS: true, HTTPSPort: 9443, CertFile: "cert", KeyFile: "key"})
	AssertEqual(t, 9000, service.Status().Port)
	AssertTrue(t, service.https)
}

func TestPHP_NewViteService_Bad(t *T) {
	service := NewViteService("", ViteOptions{})
	AssertEqual(t, "Vite", service.Name())
	AssertEqual(t, 5173, service.Status().Port)
}

func TestPHP_NewViteService_Ugly(t *T) {
	service := NewViteService(t.TempDir(), ViteOptions{Port: 3000, PackageManager: "pnpm"})
	AssertEqual(t, 3000, service.Status().Port)
	AssertEqual(t, "pnpm", service.packageManager)
}

func TestPHP_NewHorizonService_Bad(t *T) {
	service := NewHorizonService("")
	AssertEqual(t, "Horizon", service.Name())
	AssertEqual(t, 0, service.Status().Port)
}

func TestPHP_NewHorizonService_Ugly(t *T) {
	dir := filepath.Join(t.TempDir(), "app")
	service := NewHorizonService(dir)
	AssertEqual(t, dir, service.dir)
	AssertFalse(t, service.Status().Running)
}

func TestPHP_NewReverbService_Bad(t *T) {
	service := NewReverbService("", ReverbOptions{})
	AssertEqual(t, "Reverb", service.Name())
	AssertEqual(t, 8080, service.Status().Port)
}

func TestPHP_NewReverbService_Ugly(t *T) {
	service := NewReverbService(t.TempDir(), ReverbOptions{Port: 9090})
	AssertEqual(t, 9090, service.Status().Port)
	AssertFalse(t, service.Status().Running)
}

func TestPHP_NewRedisService_Bad(t *T) {
	service := NewRedisService("", RedisOptions{})
	AssertEqual(t, "Redis", service.Name())
	AssertEqual(t, 6379, service.Status().Port)
}

func TestPHP_NewRedisService_Ugly(t *T) {
	service := NewRedisService(t.TempDir(), RedisOptions{Port: 6380, ConfigFile: ax7RedisConfigFile})
	AssertEqual(t, 6380, service.Status().Port)
	AssertEqual(t, ax7RedisConfigFile, service.configFile)
}

func TestPHP_Service_Name_Good(t *T) {
	service := NewViteService(t.TempDir(), ViteOptions{})
	name := service.Name()
	AssertEqual(t, "Vite", name)
}

func TestPHP_Service_Name_Bad(t *T) {
	service := &baseService{}
	name := service.Name()
	AssertEqual(t, "", name)
}

func TestPHP_Service_Name_Ugly(t *T) {
	service := &baseService{name: "Custom Service"}
	name := service.Name()
	AssertContains(t, name, "Custom")
}

func TestPHP_Service_Status_Good(t *T) {
	service := NewRedisService(t.TempDir(), RedisOptions{Port: 6380})
	status := service.Status()
	AssertEqual(t, "Redis", status.Name)
	AssertEqual(t, 6380, status.Port)
}

func TestPHP_Service_Status_Bad(t *T) {
	service := &baseService{lastError: errors.New("failed")}
	status := service.Status()
	AssertError(t, status.Error)
}

func TestPHP_Service_Status_Ugly(t *T) {
	service := &baseService{name: "Running", running: true, port: 1}
	status := service.Status()
	AssertTrue(t, status.Running)
	AssertEqual(t, 1, status.Port)
}

func TestPHP_Service_Logs_Good(t *T) {
	dir := t.TempDir()
	path := filepath.Join(dir, "service.log")
	ax7WriteFile(t, path, "hello")
	service := &baseService{name: "Log", logPath: path}
	reader, err := service.Logs(false)
	AssertNoError(t, err)
	_ = reader.Close()
}

func TestPHP_Service_Logs_Bad(t *T) {
	service := &baseService{name: "NoLog"}
	reader, err := service.Logs(false)
	AssertError(t, err, "no log file")
	AssertEqual(t, nil, reader)
}

func TestPHP_Service_Logs_Ugly(t *T) {
	dir := t.TempDir()
	path := filepath.Join(dir, "service.log")
	ax7WriteFile(t, path, "hello")
	service := &baseService{name: "Log", logPath: path}
	reader, err := service.Logs(true)
	AssertNoError(t, err)
	_ = reader.Close()
}

func TestPHP_FrankenPHPService_Start_Good(t *T) {
	ax7LongRunningCommand(t, "php")
	service := NewFrankenPHPService(t.TempDir(), FrankenPHPOptions{})
	err := service.Start(context.Background())
	t.Cleanup(func() { _ = service.Stop() })
	AssertNoError(t, err)
}

func TestPHP_FrankenPHPService_Start_Bad(t *T) {
	t.Setenv("PATH", t.TempDir())
	service := NewFrankenPHPService(t.TempDir(), FrankenPHPOptions{})
	err := service.Start(context.Background())
	AssertError(t, err)
}

func TestPHP_FrankenPHPService_Start_Ugly(t *T) {
	ax7LongRunningCommand(t, "php")
	service := NewFrankenPHPService(t.TempDir(), FrankenPHPOptions{HTTPS: true, CertFile: "cert", KeyFile: "key"})
	service.running = true
	err := service.Start(context.Background())
	t.Cleanup(func() { _ = service.Stop() })
	AssertError(t, err, "already running")
}

func TestPHP_FrankenPHPService_Stop_Good(t *T) {
	service := NewFrankenPHPService(t.TempDir(), FrankenPHPOptions{})
	err := service.Stop()
	AssertNoError(t, err)
}

func TestPHP_FrankenPHPService_Stop_Bad(t *T) {
	service := NewFrankenPHPService("", FrankenPHPOptions{})
	err := service.Stop()
	AssertNoError(t, err)
}

func TestPHP_FrankenPHPService_Stop_Ugly(t *T) {
	ax7LongRunningCommand(t, "php")
	service := NewFrankenPHPService(t.TempDir(), FrankenPHPOptions{})
	RequireNoError(t, service.Start(context.Background()))
	err := service.Stop()
	AssertNoError(t, err)
}

func TestPHP_ViteService_Start_Good(t *T) {
	ax7LongRunningCommand(t, "npm")
	service := NewViteService(t.TempDir(), ViteOptions{PackageManager: "npm"})
	err := service.Start(context.Background())
	t.Cleanup(func() { _ = service.Stop() })
	AssertNoError(t, err)
}

func TestPHP_ViteService_Start_Bad(t *T) {
	t.Setenv("PATH", t.TempDir())
	service := NewViteService(t.TempDir(), ViteOptions{PackageManager: "npm"})
	err := service.Start(context.Background())
	AssertError(t, err)
}

func TestPHP_ViteService_Start_Ugly(t *T) {
	ax7LongRunningCommand(t, "yarn")
	service := NewViteService(t.TempDir(), ViteOptions{PackageManager: "yarn"})
	err := service.Start(context.Background())
	t.Cleanup(func() { _ = service.Stop() })
	AssertNoError(t, err)
}

func TestPHP_ViteService_Stop_Good(t *T) {
	service := NewViteService(t.TempDir(), ViteOptions{})
	err := service.Stop()
	AssertNoError(t, err)
}

func TestPHP_ViteService_Stop_Bad(t *T) {
	service := NewViteService("", ViteOptions{})
	err := service.Stop()
	AssertNoError(t, err)
}

func TestPHP_ViteService_Stop_Ugly(t *T) {
	ax7LongRunningCommand(t, "npm")
	service := NewViteService(t.TempDir(), ViteOptions{PackageManager: "npm"})
	RequireNoError(t, service.Start(context.Background()))
	err := service.Stop()
	AssertNoError(t, err)
}

func TestPHP_HorizonService_Start_Good(t *T) {
	ax7LongRunningCommand(t, "php")
	service := NewHorizonService(t.TempDir())
	err := service.Start(context.Background())
	t.Cleanup(func() { _ = service.Stop() })
	AssertNoError(t, err)
}

func TestPHP_HorizonService_Start_Bad(t *T) {
	t.Setenv("PATH", t.TempDir())
	service := NewHorizonService(t.TempDir())
	err := service.Start(context.Background())
	AssertError(t, err)
}

func TestPHP_HorizonService_Start_Ugly(t *T) {
	ax7LongRunningCommand(t, "php")
	service := NewHorizonService(t.TempDir())
	service.running = true
	err := service.Start(context.Background())
	t.Cleanup(func() { _ = service.Stop() })
	AssertError(t, err)
}

func TestPHP_HorizonService_Stop_Good(t *T) {
	service := NewHorizonService(t.TempDir())
	err := service.Stop()
	AssertNoError(t, err)
}

func TestPHP_HorizonService_Stop_Bad(t *T) {
	t.Setenv("PATH", t.TempDir())
	service := NewHorizonService(t.TempDir())
	err := service.Stop()
	AssertNoError(t, err)
}

func TestPHP_HorizonService_Stop_Ugly(t *T) {
	ax7LongRunningCommand(t, "php")
	service := NewHorizonService(t.TempDir())
	RequireNoError(t, service.Start(context.Background()))
	err := service.Stop()
	AssertNoError(t, err)
}

func TestPHP_ReverbService_Start_Good(t *T) {
	ax7LongRunningCommand(t, "php")
	service := NewReverbService(t.TempDir(), ReverbOptions{})
	err := service.Start(context.Background())
	t.Cleanup(func() { _ = service.Stop() })
	AssertNoError(t, err)
}

func TestPHP_ReverbService_Start_Bad(t *T) {
	t.Setenv("PATH", t.TempDir())
	service := NewReverbService(t.TempDir(), ReverbOptions{})
	err := service.Start(context.Background())
	AssertError(t, err)
}

func TestPHP_ReverbService_Start_Ugly(t *T) {
	ax7LongRunningCommand(t, "php")
	service := NewReverbService(t.TempDir(), ReverbOptions{Port: 9090})
	err := service.Start(context.Background())
	t.Cleanup(func() { _ = service.Stop() })
	AssertNoError(t, err)
}

func TestPHP_ReverbService_Stop_Good(t *T) {
	service := NewReverbService(t.TempDir(), ReverbOptions{})
	err := service.Stop()
	AssertNoError(t, err)
}

func TestPHP_ReverbService_Stop_Bad(t *T) {
	service := NewReverbService("", ReverbOptions{})
	err := service.Stop()
	AssertNoError(t, err)
}

func TestPHP_ReverbService_Stop_Ugly(t *T) {
	ax7LongRunningCommand(t, "php")
	service := NewReverbService(t.TempDir(), ReverbOptions{})
	RequireNoError(t, service.Start(context.Background()))
	err := service.Stop()
	AssertNoError(t, err)
}

func TestPHP_RedisService_Start_Good(t *T) {
	ax7LongRunningCommand(t, ax7RedisServer)
	service := NewRedisService(t.TempDir(), RedisOptions{})
	err := service.Start(context.Background())
	t.Cleanup(func() { _ = service.Stop() })
	AssertNoError(t, err)
}

func TestPHP_RedisService_Start_Bad(t *T) {
	t.Setenv("PATH", t.TempDir())
	service := NewRedisService(t.TempDir(), RedisOptions{})
	err := service.Start(context.Background())
	AssertError(t, err)
}

func TestPHP_RedisService_Start_Ugly(t *T) {
	ax7LongRunningCommand(t, ax7RedisServer)
	service := NewRedisService(t.TempDir(), RedisOptions{ConfigFile: ax7RedisConfigFile})
	err := service.Start(context.Background())
	t.Cleanup(func() { _ = service.Stop() })
	AssertNoError(t, err)
}

func TestPHP_RedisService_Stop_Good(t *T) {
	service := NewRedisService(t.TempDir(), RedisOptions{})
	err := service.Stop()
	AssertNoError(t, err)
}

func TestPHP_RedisService_Stop_Bad(t *T) {
	t.Setenv("PATH", t.TempDir())
	service := NewRedisService(t.TempDir(), RedisOptions{})
	err := service.Stop()
	AssertNoError(t, err)
}

func TestPHP_RedisService_Stop_Ugly(t *T) {
	ax7LongRunningCommand(t, ax7RedisServer)
	ax7LongRunningCommand(t, "redis-cli")
	service := NewRedisService(t.TempDir(), RedisOptions{})
	RequireNoError(t, service.Start(context.Background()))
	err := service.Stop()
	AssertNoError(t, err)
}

func TestPHP_NewDevServer_Bad(t *T) {
	server := NewDevServer(Options{})
	AssertNotNil(t, server)
	AssertLen(t, server.Services(), 0)
}

func TestPHP_NewDevServer_Ugly(t *T) {
	server := NewDevServer(Options{Services: []DetectedService{ServiceRedis}, RedisPort: 6380})
	AssertEqual(t, 6380, server.opts.RedisPort)
	AssertContains(t, server.opts.Services, ServiceRedis)
}

func TestPHP_DevServer_Start_Good(t *T) {
	ax7LongRunningCommand(t, "php")
	dir := ax7LaravelProject(t)
	server := NewDevServer(Options{Dir: dir, Services: []DetectedService{ServiceFrankenPHP}})
	err := server.Start(context.Background(), Options{Dir: dir, Services: []DetectedService{ServiceFrankenPHP}})
	t.Cleanup(func() { _ = server.Stop() })
	AssertNoError(t, err)
}

func TestPHP_DevServer_Start_Ugly(t *T) {
	server := NewDevServer(Options{})
	server.running = true
	err := server.Start(context.Background(), Options{})
	AssertError(t, err, "already running")
}

func TestPHP_DevServer_Stop_Bad(t *T) {
	server := NewDevServer(Options{})
	server.running = true
	server.services = []Service{&ax7Service{name: "bad", stopErr: errors.New("stop failed")}}
	err := server.Stop()
	AssertError(t, err, "errors stopping")
}

func TestPHP_DevServer_Stop_Ugly(t *T) {
	server := NewDevServer(Options{})
	server.running = true
	server.cancel = func() {
		// Intentionally empty; this test only verifies Stop calls the hook.
	}
	err := server.Stop()
	AssertNoError(t, err)
}

func TestPHP_DevServer_Logs_Ugly(t *T) {
	server := NewDevServer(Options{})
	reader, err := server.Logs("missing", false)
	AssertError(t, err, "service not found")
	AssertEqual(t, nil, reader)
}

func TestPHP_DevServer_Status_Bad(t *T) {
	server := NewDevServer(Options{})
	status := server.Status()
	AssertLen(t, status, 0)
}

func TestPHP_DevServer_Status_Ugly(t *T) {
	server := NewDevServer(Options{})
	server.services = []Service{&ax7Service{name: "svc", status: ServiceStatus{Name: "svc", Running: true}}}
	status := server.Status()
	AssertTrue(t, status[0].Running)
}

func TestPHP_DevServer_IsRunning_Bad(t *T) {
	server := NewDevServer(Options{})
	running := server.IsRunning()
	AssertFalse(t, running)
	AssertLen(t, server.Services(), 0)
}

func TestPHP_DevServer_IsRunning_Ugly(t *T) {
	server := NewDevServer(Options{})
	server.running = true
	AssertTrue(t, server.IsRunning())
}

func TestPHP_DevServer_Services_Bad(t *T) {
	server := NewDevServer(Options{})
	services := server.Services()
	AssertLen(t, services, 0)
}

func TestPHP_DevServer_Services_Ugly(t *T) {
	server := NewDevServer(Options{})
	server.services = []Service{&ax7Service{name: "svc"}}
	services := server.Services()
	AssertEqual(t, "svc", services[0].Name())
}

func TestPHP_Reader_Read_Good(t *T) {
	path := filepath.Join(t.TempDir(), ax7TailLog)
	ax7WriteFile(t, path, "line")
	file, err := os.Open(path)
	RequireNoError(t, err)
	reader := newTailReader(file)
	buf := make([]byte, 8)
	n, err := reader.Read(buf)
	AssertNoError(t, err)
	AssertEqual(t, "line", string(buf[:n]))
}

func TestPHP_Reader_Read_Bad(t *T) {
	path := filepath.Join(t.TempDir(), ax7TailLog)
	ax7WriteFile(t, path, "line")
	file, err := os.Open(path)
	RequireNoError(t, err)
	reader := newTailReader(file)
	RequireNoError(t, reader.Close())
	n, err := reader.Read(make([]byte, 8))
	AssertEqual(t, 0, n)
	AssertEqual(t, io.EOF, err)
}

func TestPHP_Reader_Read_Ugly(t *T) {
	path := filepath.Join(t.TempDir(), ax7TailLog)
	ax7WriteFile(t, path, "abc")
	file, err := os.Open(path)
	RequireNoError(t, err)
	reader := newTailReader(file)
	buf := make([]byte, 1)
	n, err := reader.Read(buf)
	AssertNoError(t, err)
	AssertEqual(t, 1, n)
}

func TestPHP_Reader_Close_Good(t *T) {
	path := filepath.Join(t.TempDir(), ax7TailLog)
	ax7WriteFile(t, path, "line")
	file, err := os.Open(path)
	RequireNoError(t, err)
	reader := newTailReader(file)
	err = reader.Close()
	AssertNoError(t, err)
}

func TestPHP_Reader_Close_Bad(t *T) {
	path := filepath.Join(t.TempDir(), ax7TailLog)
	ax7WriteFile(t, path, "line")
	file, err := os.Open(path)
	RequireNoError(t, err)
	reader := newTailReader(file)
	RequireNoError(t, reader.Close())
	err = reader.Close()
	AssertError(t, err)
}

func TestPHP_Reader_Close_Ugly(t *T) {
	path := filepath.Join(t.TempDir(), ax7TailLog)
	ax7WriteFile(t, path, "")
	file, err := os.Open(path)
	RequireNoError(t, err)
	reader := newTailReader(file)
	err = reader.Close()
	AssertNoError(t, err)
}

func TestPHP_ServiceReader_Read_Good(t *T) {
	reader := newMultiServiceReader([]Service{&ax7Service{name: "svc"}}, []io.ReadCloser{io.NopCloser(strings.NewReader("log"))}, false)
	buf := make([]byte, 32)
	n, err := reader.Read(buf)
	AssertNoError(t, err)
	AssertContains(t, string(buf[:n]), "[svc] log")
}

func TestPHP_ServiceReader_Read_Bad(t *T) {
	reader := newMultiServiceReader(nil, nil, false)
	n, err := reader.Read(make([]byte, 4))
	AssertEqual(t, 0, n)
	AssertEqual(t, io.EOF, err)
}

func TestPHP_ServiceReader_Read_Ugly(t *T) {
	reader := newMultiServiceReader(nil, nil, true)
	n, err := reader.Read(make([]byte, 4))
	AssertNoError(t, err)
	AssertEqual(t, 0, n)
}

func TestPHP_ServiceReader_Close_Good(t *T) {
	reader := newMultiServiceReader(nil, []io.ReadCloser{io.NopCloser(strings.NewReader(""))}, false)
	err := reader.Close()
	AssertNoError(t, err)
}

func TestPHP_ServiceReader_Close_Bad(t *T) {
	reader := newMultiServiceReader(nil, []io.ReadCloser{ax7FailingCloser{}}, false)
	err := reader.Close()
	AssertError(t, err, "close failed")
}

func TestPHP_ServiceReader_Close_Ugly(t *T) {
	reader := newMultiServiceReader(nil, nil, false)
	err := reader.Close()
	AssertNoError(t, err)
}

func TestPHP_LinkPackages_Ugly(t *T) {
	dir := ax7PHPProject(t)
	pkg := t.TempDir()
	ax7WriteFile(t, filepath.Join(pkg, composerJSONFile), `{"name":"acme/package","version":"dev-main"}`)
	err := LinkPackages(dir, []string{pkg})
	AssertNoError(t, err)
}

func TestPHP_UnlinkPackages_Ugly(t *T) {
	dir := ax7PHPProject(t)
	pkg := t.TempDir()
	ax7WriteFile(t, filepath.Join(pkg, composerJSONFile), `{"name":"acme/package"}`)
	RequireNoError(t, LinkPackages(dir, []string{pkg}))
	err := UnlinkPackages(dir, []string{"acme/package"})
	AssertNoError(t, err)
}

func TestPHP_UpdatePackages_Ugly(t *T) {
	bin := ax7BinPath(t)
	ax7Executable(t, bin, "composer", ax7ExitOKScript)
	dir := ax7PHPProject(t)
	err := UpdatePackages(dir, []string{})
	AssertNoError(t, err)
}

func TestPHP_ListLinkedPackages_Ugly(t *T) {
	dir := ax7PHPProject(t)
	packages, err := ListLinkedPackages(dir)
	AssertNoError(t, err)
	AssertLen(t, packages, 0)
}
