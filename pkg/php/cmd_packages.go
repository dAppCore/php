package php

import (
	"os"

	core "dappco.re/go"
	"dappco.re/go/cli/pkg/cli"
	"dappco.re/go/i18n"
)

func addPHPPackagesCommands(c *core.Core, prefix string) {
	phpHelpCommand(c, phpCommandPath(prefix, "packages"), i18n.T("cmd.php.packages.short"))
	addPHPPackagesLinkCommand(c, prefix)
	addPHPPackagesUnlinkCommand(c, prefix)
	addPHPPackagesUpdateCommand(c, prefix)
	addPHPPackagesListCommand(c, prefix)
}

func addPHPPackagesLinkCommand(c *core.Core, prefix string) {
	path := phpCommandPath(prefix, "packages/link")
	phpErrorCommand(c, path, i18n.T("cmd.php.packages.link.short"), func(opts core.Options) error {
		args := phpCommandLineFor(path, opts).Args()
		if len(args) < 1 {
			return phpErr("requires at least 1 arg(s), only received %d", len(args))
		}

		cwd, err := os.Getwd()
		if err != nil {
			return phpErr(cliWrapErrorFormat, i18n.T(i18nFailGetKey, workingDirectorySubject), err)
		}

		cli.Print(cliLabelValueBlankFormat, dimStyle.Render(i18n.T(cmdPHPLabelKey)), i18n.T("cmd.php.packages.link.linking"))

		if err := LinkPackages(cwd, args); err != nil {
			return phpErr(cliWrapErrorFormat, i18n.T("i18n.fail.link", "packages"), err)
		}

		cli.Print(cliSectionLabelValueFormat, successStyle.Render(i18n.Label("done")), i18n.T("cmd.php.packages.link.done"))
		return nil
	})
}

func addPHPPackagesUnlinkCommand(c *core.Core, prefix string) {
	path := phpCommandPath(prefix, "packages/unlink")
	phpErrorCommand(c, path, i18n.T("cmd.php.packages.unlink.short"), func(opts core.Options) error {
		args := phpCommandLineFor(path, opts).Args()
		if len(args) < 1 {
			return phpErr("requires at least 1 arg(s), only received %d", len(args))
		}

		cwd, err := os.Getwd()
		if err != nil {
			return phpErr(cliWrapErrorFormat, i18n.T(i18nFailGetKey, workingDirectorySubject), err)
		}

		cli.Print(cliLabelValueBlankFormat, dimStyle.Render(i18n.T(cmdPHPLabelKey)), i18n.T("cmd.php.packages.unlink.unlinking"))

		if err := UnlinkPackages(cwd, args); err != nil {
			return phpErr(cliWrapErrorFormat, i18n.T("i18n.fail.unlink", "packages"), err)
		}

		cli.Print(cliSectionLabelValueFormat, successStyle.Render(i18n.Label("done")), i18n.T("cmd.php.packages.unlink.done"))
		return nil
	})
}

func addPHPPackagesUpdateCommand(c *core.Core, prefix string) {
	path := phpCommandPath(prefix, "packages/update")
	phpErrorCommand(c, path, i18n.T("cmd.php.packages.update.short"), func(opts core.Options) error {
		args := phpCommandLineFor(path, opts).Args()
		cwd, err := os.Getwd()
		if err != nil {
			return phpErr(cliWrapErrorFormat, i18n.T(i18nFailGetKey, workingDirectorySubject), err)
		}

		cli.Print(cliLabelValueBlankFormat, dimStyle.Render(i18n.T(cmdPHPLabelKey)), i18n.T("cmd.php.packages.update.updating"))

		if err := UpdatePackages(cwd, args); err != nil {
			return phpErr(cliWrapErrorFormat, i18n.T("cmd.php.error.update_packages"), err)
		}

		cli.Print(cliSectionLabelValueFormat, successStyle.Render(i18n.Label("done")), i18n.T("cmd.php.packages.update.done"))
		return nil
	})
}

func addPHPPackagesListCommand(c *core.Core, prefix string) {
	path := phpCommandPath(prefix, "packages/list")
	phpErrorCommand(c, path, i18n.T("cmd.php.packages.list.short"), func(opts core.Options) error {
		cwd, err := os.Getwd()
		if err != nil {
			return phpErr(cliWrapErrorFormat, i18n.T(i18nFailGetKey, workingDirectorySubject), err)
		}

		packages, err := ListLinkedPackages(cwd)
		if err != nil {
			return phpErr(cliWrapErrorFormat, i18n.T("i18n.fail.list", "packages"), err)
		}

		if len(packages) == 0 {
			cli.Print(cliLabelValueFormat, dimStyle.Render(i18n.T(cmdPHPLabelKey)), i18n.T("cmd.php.packages.list.none_found"))
			return nil
		}

		cli.Print(cliLabelValueBlankFormat, dimStyle.Render(i18n.T(cmdPHPLabelKey)), i18n.T("cmd.php.packages.list.linked"))

		for _, pkg := range packages {
			name := pkg.Name
			if name == "" {
				name = i18n.T("cmd.php.packages.list.unknown")
			}
			version := pkg.Version
			if version == "" {
				version = "dev"
			}

			cli.Print("  %s %s\n", successStyle.Render("*"), name)
			cli.Print("    %s %s\n", dimStyle.Render(i18n.Label("path")), pkg.Path)
			cli.Print("    %s %s\n", dimStyle.Render(i18n.Label("version")), version)
			cli.Blank()
		}

		return nil
	})
}
