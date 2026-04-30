package php

import (
	"context"
	"errors"
	"os"
	"strings"

	core "dappco.re/go"
	"dappco.re/go/cli/pkg/cli"
	"dappco.re/go/i18n"
)

func addPHPBuildCommand(c *core.Core, prefix string) {
	path := phpCommandPath(prefix, "build")
	phpErrorCommand(c, path, i18n.T("cmd.php.build.short"), func(opts core.Options) error {
		line := phpCommandLineFor(path, opts)
		cwd, err := os.Getwd()
		if err != nil {
			return phpErr(cliWrapErrorFormat, i18n.T(i18nFailGetKey, workingDirectorySubject), err)
		}

		ctx := context.Background()

		switch strings.ToLower(line.String("type", "")) {
		case "linuxkit":
			return runPHPBuildLinuxKit(ctx, cwd, linuxKitBuildOptions{
				OutputPath: line.String("output", ""),
				Format:     line.String("format", ""),
				Template:   line.String("template", ""),
			})
		default:
			return runPHPBuildDocker(ctx, cwd, dockerBuildOptions{
				ImageName:  line.String("name", ""),
				Tag:        line.String("tag", ""),
				Platform:   line.String("platform", ""),
				Dockerfile: line.String("dockerfile", ""),
				NoCache:    line.Bool("no-cache"),
			})
		}
	})
}

type dockerBuildOptions struct {
	ImageName  string
	Tag        string
	Platform   string
	Dockerfile string
	NoCache    bool
}

type linuxKitBuildOptions struct {
	OutputPath string
	Format     string
	Template   string
}

func runPHPBuildDocker(ctx context.Context, projectDir string, opts dockerBuildOptions) error {
	if !IsPHPProject(projectDir) {
		return errors.New(i18n.T("cmd.php.error.not_php"))
	}

	cli.Print(cliLabelValueBlankFormat, dimStyle.Render(i18n.T(cmdPHPLabelKey)), i18n.T("cmd.php.build.building_docker"))

	// Show detected configuration
	config, err := DetectDockerfileConfig(projectDir)
	if err != nil {
		return phpErr(cliWrapErrorFormat, i18n.T("i18n.fail.detect", "project configuration"), err)
	}

	cli.Print(cliLabelValueFormat, dimStyle.Render(i18n.T("cmd.php.build.php_version")), config.PHPVersion)
	cli.Print(cliLabelBoolFormat, dimStyle.Render(i18n.T("cmd.php.build.laravel")), config.IsLaravel)
	cli.Print(cliLabelBoolFormat, dimStyle.Render(i18n.T("cmd.php.build.octane")), config.HasOctane)
	cli.Print(cliLabelBoolFormat, dimStyle.Render(i18n.T("cmd.php.build.frontend")), config.HasAssets)
	if len(config.PHPExtensions) > 0 {
		cli.Print(cliLabelValueFormat, dimStyle.Render(i18n.T("cmd.php.build.extensions")), strings.Join(config.PHPExtensions, ", "))
	}
	cli.Blank()

	// Build options
	buildOpts := DockerBuildOptions{
		ProjectDir:   projectDir,
		ImageName:    opts.ImageName,
		Tag:          opts.Tag,
		Platform:     opts.Platform,
		Dockerfile:   opts.Dockerfile,
		NoBuildCache: opts.NoCache,
		Output:       os.Stdout,
	}

	if buildOpts.ImageName == "" {
		buildOpts.ImageName = GetLaravelAppName(projectDir)
		if buildOpts.ImageName == "" {
			buildOpts.ImageName = "php-app"
		}
		// Sanitize for Docker
		buildOpts.ImageName = strings.ToLower(strings.ReplaceAll(buildOpts.ImageName, " ", "-"))
	}

	if buildOpts.Tag == "" {
		buildOpts.Tag = "latest"
	}

	cli.Print("%s %s:%s\n", dimStyle.Render(i18n.Label("image")), buildOpts.ImageName, buildOpts.Tag)
	if opts.Platform != "" {
		cli.Print(cliLabelValueFormat, dimStyle.Render(i18n.T("cmd.php.build.platform")), opts.Platform)
	}
	cli.Blank()

	if err := BuildDocker(ctx, buildOpts); err != nil {
		return phpErr(cliWrapErrorFormat, i18n.T("i18n.fail.build"), err)
	}

	cli.Print(cliSectionLabelValueFormat, successStyle.Render(i18n.Label("done")), i18n.T("common.success.completed", map[string]any{"Action": "Docker image built"}))
	cli.Print("%s docker run -p 80:80 -p 443:443 %s:%s\n",
		dimStyle.Render(i18n.T("cmd.php.build.docker_run_with")),
		buildOpts.ImageName, buildOpts.Tag)

	return nil
}

func runPHPBuildLinuxKit(ctx context.Context, projectDir string, opts linuxKitBuildOptions) error {
	if !IsPHPProject(projectDir) {
		return errors.New(i18n.T("cmd.php.error.not_php"))
	}

	cli.Print(cliLabelValueBlankFormat, dimStyle.Render(i18n.T(cmdPHPLabelKey)), i18n.T("cmd.php.build.building_linuxkit"))

	buildOpts := LinuxKitBuildOptions{
		ProjectDir: projectDir,
		OutputPath: opts.OutputPath,
		Format:     opts.Format,
		Template:   opts.Template,
		Output:     os.Stdout,
	}

	if buildOpts.Format == "" {
		buildOpts.Format = "qcow2"
	}
	if buildOpts.Template == "" {
		buildOpts.Template = defaultLinuxKitTemplateName
	}

	cli.Print(cliLabelValueFormat, dimStyle.Render(i18n.Label("template")), buildOpts.Template)
	cli.Print(cliLabelValueFormat, dimStyle.Render(i18n.T("cmd.php.build.format")), buildOpts.Format)
	cli.Blank()

	if err := BuildLinuxKit(ctx, buildOpts); err != nil {
		return phpErr(cliWrapErrorFormat, i18n.T("i18n.fail.build"), err)
	}

	cli.Print(cliSectionLabelValueFormat, successStyle.Render(i18n.Label("done")), i18n.T("common.success.completed", map[string]any{"Action": "LinuxKit image built"}))
	return nil
}

func addPHPServeCommand(c *core.Core, prefix string) {
	path := phpCommandPath(prefix, "serve")
	phpErrorCommand(c, path, i18n.T("cmd.php.serve.short"), func(opts core.Options) error {
		line := phpCommandLineFor(path, opts)
		imageName, err := resolveServeImageName(line.String("name", ""))
		if err != nil {
			return err
		}

		ctx := context.Background()
		serveTag := line.String("tag", "")
		servePort := line.Int("port", 0)
		serveHTTPSPort := line.Int("https-port", 0)
		serveDetach := line.Bool("detach", "d")

		serveOpts := ServeOptions{
			ImageName:     imageName,
			Tag:           serveTag,
			ContainerName: line.String("container", ""),
			Port:          servePort,
			HTTPSPort:     serveHTTPSPort,
			Detach:        serveDetach,
			EnvFile:       line.String("env-file", ""),
			Output:        os.Stdout,
		}

		cli.Print(cliLabelValueBlankFormat, dimStyle.Render(i18n.T(cmdPHPLabelKey)), i18n.ProgressSubject("run", "production container"))
		cli.Print("%s %s:%s\n", dimStyle.Render(i18n.Label("image")), imageName, displayServeTag(serveTag))

		effectivePort, effectiveHTTPSPort := effectiveServePorts(servePort, serveHTTPSPort)
		cli.Print("%s http://localhost:%d, https://localhost:%d\n",
			dimStyle.Render("Ports:"), effectivePort, effectiveHTTPSPort)
		cli.Blank()

		if err := ServeProduction(ctx, serveOpts); err != nil {
			return phpErr(cliWrapErrorFormat, i18n.T("i18n.fail.start", "container"), err)
		}

		if !serveDetach {
			cli.Print(cliSectionLabelValueFormat, dimStyle.Render(i18n.T(cmdPHPLabelKey)), i18n.T("cmd.php.serve.stopped"))
		}

		return nil
	})
}

func resolveServeImageName(imageName string) (string, error) {
	if imageName != "" {
		return imageName, nil
	}

	cwd, err := os.Getwd()
	if err == nil {
		if appName := GetLaravelAppName(cwd); appName != "" {
			return strings.ToLower(strings.ReplaceAll(appName, " ", "-")), nil
		}
	}

	return "", errors.New(i18n.T("cmd.php.serve.name_required"))
}

func displayServeTag(tag string) string {
	if tag == "" {
		return "latest"
	}
	return tag
}

func effectiveServePorts(port, httpsPort int) (int, int) {
	effectivePort := port
	if effectivePort == 0 {
		effectivePort = 80
	}

	effectiveHTTPSPort := httpsPort
	if effectiveHTTPSPort == 0 {
		effectiveHTTPSPort = 443
	}

	return effectivePort, effectiveHTTPSPort
}

func addPHPShellCommand(c *core.Core, prefix string) {
	path := phpCommandPath(prefix, "shell")
	phpErrorCommand(c, path, i18n.T("cmd.php.shell.short"), func(opts core.Options) error {
		args := phpCommandLineFor(path, opts).Args()
		if len(args) != 1 {
			return phpErr("requires exactly 1 arg(s), received %d", len(args))
		}

		ctx := context.Background()

		cli.Print(cliLabelValueFormat, dimStyle.Render(i18n.T(cmdPHPLabelKey)), i18n.T("cmd.php.shell.opening", map[string]interface{}{"Container": args[0]}))

		if err := Shell(ctx, args[0]); err != nil {
			return phpErr(cliWrapErrorFormat, i18n.T("i18n.fail.open", "shell"), err)
		}

		return nil
	})
}
