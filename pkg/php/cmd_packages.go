package php

import (
	"os"

	"forge.lthn.ai/core/cli/pkg/cli"
	"forge.lthn.ai/core/go-i18n"
)

func addPHPPackagesCommands(parent *cli.Command) {
	packagesCmd := &cli.Command{
		Use:   "packages",
		Short: i18n.T("cmd.php.packages.short"),
		Long:  i18n.T("cmd.php.packages.long"),
	}
	parent.AddCommand(packagesCmd)

	addPHPPackagesLinkCommand(packagesCmd)
	addPHPPackagesUnlinkCommand(packagesCmd)
	addPHPPackagesUpdateCommand(packagesCmd)
	addPHPPackagesListCommand(packagesCmd)
}

func addPHPPackagesLinkCommand(parent *cli.Command) {
	linkCmd := &cli.Command{
		Use:   "link [paths...]",
		Short: i18n.T("cmd.php.packages.link.short"),
		Long:  i18n.T("cmd.php.packages.link.long"),
		Args:  cli.MinimumNArgs(1),
		RunE: func(cmd *cli.Command, args []string) error {
			cwd, err := os.Getwd()
			if err != nil {
				return cli.Err("%s: %w", i18n.T("i18n.fail.get", "working directory"), err)
			}

			cli.Print("%s %s\n\n", dimStyle.Render(i18n.T("cmd.php.label.php")), i18n.T("cmd.php.packages.link.linking"))

			if err := LinkPackages(cwd, args); err != nil {
				return cli.Err("%s: %w", i18n.T("i18n.fail.link", "packages"), err)
			}

			cli.Print("\n%s %s\n", successStyle.Render(i18n.Label("done")), i18n.T("cmd.php.packages.link.done"))
			return nil
		},
	}

	parent.AddCommand(linkCmd)
}

func addPHPPackagesUnlinkCommand(parent *cli.Command) {
	unlinkCmd := &cli.Command{
		Use:   "unlink [packages...]",
		Short: i18n.T("cmd.php.packages.unlink.short"),
		Long:  i18n.T("cmd.php.packages.unlink.long"),
		Args:  cli.MinimumNArgs(1),
		RunE: func(cmd *cli.Command, args []string) error {
			cwd, err := os.Getwd()
			if err != nil {
				return cli.Err("%s: %w", i18n.T("i18n.fail.get", "working directory"), err)
			}

			cli.Print("%s %s\n\n", dimStyle.Render(i18n.T("cmd.php.label.php")), i18n.T("cmd.php.packages.unlink.unlinking"))

			if err := UnlinkPackages(cwd, args); err != nil {
				return cli.Err("%s: %w", i18n.T("i18n.fail.unlink", "packages"), err)
			}

			cli.Print("\n%s %s\n", successStyle.Render(i18n.Label("done")), i18n.T("cmd.php.packages.unlink.done"))
			return nil
		},
	}

	parent.AddCommand(unlinkCmd)
}

func addPHPPackagesUpdateCommand(parent *cli.Command) {
	updateCmd := &cli.Command{
		Use:   "update [packages...]",
		Short: i18n.T("cmd.php.packages.update.short"),
		Long:  i18n.T("cmd.php.packages.update.long"),
		RunE: func(cmd *cli.Command, args []string) error {
			cwd, err := os.Getwd()
			if err != nil {
				return cli.Err("%s: %w", i18n.T("i18n.fail.get", "working directory"), err)
			}

			cli.Print("%s %s\n\n", dimStyle.Render(i18n.T("cmd.php.label.php")), i18n.T("cmd.php.packages.update.updating"))

			if err := UpdatePackages(cwd, args); err != nil {
				return cli.Err("%s: %w", i18n.T("cmd.php.error.update_packages"), err)
			}

			cli.Print("\n%s %s\n", successStyle.Render(i18n.Label("done")), i18n.T("cmd.php.packages.update.done"))
			return nil
		},
	}

	parent.AddCommand(updateCmd)
}

func addPHPPackagesListCommand(parent *cli.Command) {
	listCmd := &cli.Command{
		Use:   "list",
		Short: i18n.T("cmd.php.packages.list.short"),
		Long:  i18n.T("cmd.php.packages.list.long"),
		RunE: func(cmd *cli.Command, args []string) error {
			cwd, err := os.Getwd()
			if err != nil {
				return cli.Err("%s: %w", i18n.T("i18n.fail.get", "working directory"), err)
			}

			packages, err := ListLinkedPackages(cwd)
			if err != nil {
				return cli.Err("%s: %w", i18n.T("i18n.fail.list", "packages"), err)
			}

			if len(packages) == 0 {
				cli.Print("%s %s\n", dimStyle.Render(i18n.T("cmd.php.label.php")), i18n.T("cmd.php.packages.list.none_found"))
				return nil
			}

			cli.Print("%s %s\n\n", dimStyle.Render(i18n.T("cmd.php.label.php")), i18n.T("cmd.php.packages.list.linked"))

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
		},
	}

	parent.AddCommand(listCmd)
}
