package php

import (
	"context"
	"os"
	"time"

	core "dappco.re/go"
	"dappco.re/go/cli/pkg/cli"
	"dappco.re/go/i18n"
)

// Deploy command styles (aliases to shared)
var (
	phpDeployStyle        = cli.SuccessStyle
	phpDeployPendingStyle = cli.WarningStyle
	phpDeployFailedStyle  = cli.ErrorStyle
)

func addPHPDeployCommands(c *core.Core, prefix string) {
	// Main deploy command
	addPHPDeployCommand(c, prefix)

	// Deploy status subcommand (using colon notation: deploy:status)
	addPHPDeployStatusCommand(c, prefix)

	// Deploy rollback subcommand
	addPHPDeployRollbackCommand(c, prefix)

	// Deploy list subcommand
	addPHPDeployListCommand(c, prefix)
}

func addPHPDeployCommand(c *core.Core, prefix string) {
	path := phpCommandPath(prefix, "deploy")
	phpErrorCommand(c, path, i18n.T("cmd.php.deploy.short"), func(options core.Options) error {
		line := phpCommandLineFor(path, options)
		cwd, err := os.Getwd()
		if err != nil {
			return phpErr(cliWrapErrorFormat, i18n.T(i18nFailGetKey, workingDirectorySubject), err)
		}

		env := EnvProduction
		if line.Bool("staging") {
			env = EnvStaging
		}

		cli.Print(cliLabelValueBlankFormat, dimStyle.Render(i18n.T(cmdPHPDeployLabelKey)), i18n.T("cmd.php.deploy.deploying", map[string]interface{}{"Environment": env}))

		ctx := context.Background()

		deployOpts := DeployOptions{
			Dir:         cwd,
			Environment: env,
			Force:       line.Bool("force"),
			Wait:        line.Bool("wait"),
		}

		status, err := Deploy(ctx, deployOpts)
		if err != nil {
			return phpErr(cliWrapErrorFormat, i18n.T("cmd.php.error.deploy_failed"), err)
		}

		printDeploymentStatus(status)

		if deployOpts.Wait {
			if IsDeploymentSuccessful(status.Status) {
				cli.Print(cliSectionLabelValueFormat, successStyle.Render(i18n.Label("done")), i18n.T("common.success.completed", map[string]any{"Action": "Deployment completed"}))
			} else {
				cli.Print(cliSectionLabelValueFormat, errorStyle.Render(i18n.Label("warning")), i18n.T("cmd.php.deploy.warning_status", map[string]interface{}{"Status": status.Status}))
			}
		} else {
			cli.Print(cliSectionLabelValueFormat, successStyle.Render(i18n.Label("done")), i18n.T("cmd.php.deploy.triggered"))
		}

		return nil
	})
}

func addPHPDeployStatusCommand(c *core.Core, prefix string) {
	path := phpCommandPath(prefix, "deploy:status")
	phpErrorCommand(c, path, i18n.T("cmd.php.deploy_status.short"), func(options core.Options) error {
		line := phpCommandLineFor(path, options)
		cwd, err := os.Getwd()
		if err != nil {
			return phpErr(cliWrapErrorFormat, i18n.T(i18nFailGetKey, workingDirectorySubject), err)
		}

		env := EnvProduction
		if line.Bool("staging") {
			env = EnvStaging
		}

		cli.Print(cliLabelValueBlankFormat, dimStyle.Render(i18n.T(cmdPHPDeployLabelKey)), i18n.ProgressSubject("check", "deployment status"))

		ctx := context.Background()

		statusOpts := StatusOptions{
			Dir:          cwd,
			Environment:  env,
			DeploymentID: line.String("id", ""),
		}

		status, err := DeployStatus(ctx, statusOpts)
		if err != nil {
			return phpErr(cliWrapErrorFormat, i18n.T(i18nFailGetKey, "status"), err)
		}

		printDeploymentStatus(status)

		return nil
	})
}

func addPHPDeployRollbackCommand(c *core.Core, prefix string) {
	path := phpCommandPath(prefix, "deploy:rollback")
	phpErrorCommand(c, path, i18n.T("cmd.php.deploy_rollback.short"), func(options core.Options) error {
		line := phpCommandLineFor(path, options)
		cwd, err := os.Getwd()
		if err != nil {
			return phpErr(cliWrapErrorFormat, i18n.T(i18nFailGetKey, workingDirectorySubject), err)
		}

		env := EnvProduction
		if line.Bool("staging") {
			env = EnvStaging
		}

		cli.Print(cliLabelValueBlankFormat, dimStyle.Render(i18n.T(cmdPHPDeployLabelKey)), i18n.T("cmd.php.deploy_rollback.rolling_back", map[string]interface{}{"Environment": env}))

		ctx := context.Background()

		rollbackOpts := RollbackOptions{
			Dir:          cwd,
			Environment:  env,
			DeploymentID: line.String("id", ""),
			Wait:         line.Bool("wait"),
		}

		status, err := Rollback(ctx, rollbackOpts)
		if err != nil {
			return phpErr(cliWrapErrorFormat, i18n.T("cmd.php.error.rollback_failed"), err)
		}

		printDeploymentStatus(status)

		if rollbackOpts.Wait {
			if IsDeploymentSuccessful(status.Status) {
				cli.Print(cliSectionLabelValueFormat, successStyle.Render(i18n.Label("done")), i18n.T("common.success.completed", map[string]any{"Action": "Rollback completed"}))
			} else {
				cli.Print(cliSectionLabelValueFormat, errorStyle.Render(i18n.Label("warning")), i18n.T("cmd.php.deploy_rollback.warning_status", map[string]interface{}{"Status": status.Status}))
			}
		} else {
			cli.Print(cliSectionLabelValueFormat, successStyle.Render(i18n.Label("done")), i18n.T("cmd.php.deploy_rollback.triggered"))
		}

		return nil
	})
}

func addPHPDeployListCommand(c *core.Core, prefix string) {
	path := phpCommandPath(prefix, "deploy:list")
	phpErrorCommand(c, path, i18n.T("cmd.php.deploy_list.short"), func(options core.Options) error {
		line := phpCommandLineFor(path, options)
		cwd, err := os.Getwd()
		if err != nil {
			return phpErr(cliWrapErrorFormat, i18n.T(i18nFailGetKey, workingDirectorySubject), err)
		}

		env := EnvProduction
		if line.Bool("staging") {
			env = EnvStaging
		}

		limit := line.Int("limit", 0)
		if limit == 0 {
			limit = 10
		}

		cli.Print(cliLabelValueBlankFormat, dimStyle.Render(i18n.T(cmdPHPDeployLabelKey)), i18n.T("cmd.php.deploy_list.recent", map[string]interface{}{"Environment": env}))

		ctx := context.Background()

		deployments, err := ListDeployments(ctx, cwd, env, limit)
		if err != nil {
			return phpErr(cliWrapErrorFormat, i18n.T("i18n.fail.list", "deployments"), err)
		}

		if len(deployments) == 0 {
			cli.Print(cliLabelValueFormat, dimStyle.Render(i18n.T("cmd.php.label.info")), i18n.T("cmd.php.deploy_list.none_found"))
			return nil
		}

		for i, d := range deployments {
			printDeploymentSummary(i+1, &d)
		}

		return nil
	})
}

func printDeploymentStatus(status *DeploymentStatus) {
	statusStyle := deploymentStatusStyle(status.Status)
	cli.Print(cliLabelValueFormat, dimStyle.Render(i18n.Label("status")), statusStyle.Render(status.Status))
	printDeploymentField(i18n.T("cmd.php.label.id"), status.ID)
	if status.URL != "" {
		printDeploymentField(i18n.Label("url"), linkStyle.Render(status.URL))
	}
	printDeploymentField(i18n.T("cmd.php.label.branch"), status.Branch)

	if status.Commit != "" {
		cli.Print(cliLabelValueFormat, dimStyle.Render(i18n.T("cmd.php.label.commit")), truncateString(status.Commit, 7))
		if status.CommitMessage != "" {
			cli.Print(cliLabelValueFormat, dimStyle.Render(i18n.T("cmd.php.label.message")), ellipsizeString(status.CommitMessage, 60))
		}
	}

	if !status.StartedAt.IsZero() {
		cli.Print(cliLabelValueFormat, dimStyle.Render(i18n.Label("started")), status.StartedAt.Format(time.RFC3339))
	}

	if !status.CompletedAt.IsZero() {
		printDeploymentCompletion(status)
	}
}

func printDeploymentSummary(index int, status *DeploymentStatus) {
	// Status with color
	statusStyle := phpDeployStyle
	switch status.Status {
	case "queued", "building", "deploying", "pending", "rolling_back":
		statusStyle = phpDeployPendingStyle
	case "failed", "error", "cancelled":
		statusStyle = phpDeployFailedStyle
	}

	// Format: #1 [finished] abc1234 - commit message (2 hours ago)
	id := status.ID
	if len(id) > 8 {
		id = id[:8]
	}

	commit := status.Commit
	if len(commit) > 7 {
		commit = commit[:7]
	}

	msg := status.CommitMessage
	if len(msg) > 40 {
		msg = msg[:37] + "..."
	}

	age := ""
	if !status.StartedAt.IsZero() {
		age = i18n.TimeAgo(status.StartedAt)
	}

	cli.Print("  %s %s %s",
		dimStyle.Render(cli.Sprintf("#%d", index)),
		statusStyle.Render(cli.Sprintf("[%s]", status.Status)),
		id,
	)

	if commit != "" {
		cli.Print(" %s", commit)
	}

	if msg != "" {
		cli.Print(" - %s", msg)
	}

	if age != "" {
		cli.Print(" %s", dimStyle.Render(cli.Sprintf("(%s)", age)))
	}

	cli.Blank()
}

func deploymentStatusStyle(status string) *cli.AnsiStyle {
	switch status {
	case "queued", "building", "deploying", "pending", "rolling_back":
		return phpDeployPendingStyle
	case "failed", "error", "cancelled":
		return phpDeployFailedStyle
	default:
		return phpDeployStyle
	}
}

func printDeploymentField(label, value string) {
	if value != "" {
		cli.Print(cliLabelValueFormat, dimStyle.Render(label), value)
	}
}

func printDeploymentCompletion(status *DeploymentStatus) {
	cli.Print(cliLabelValueFormat, dimStyle.Render(i18n.T("cmd.php.label.completed")), status.CompletedAt.Format(time.RFC3339))
	if !status.StartedAt.IsZero() {
		duration := status.CompletedAt.Sub(status.StartedAt)
		cli.Print(cliLabelValueFormat, dimStyle.Render(i18n.T("cmd.php.label.duration")), duration.Round(time.Second))
	}
}

func truncateString(value string, maxLen int) string {
	if len(value) <= maxLen {
		return value
	}
	return value[:maxLen]
}

func ellipsizeString(value string, maxLen int) string {
	if len(value) <= maxLen {
		return value
	}
	return value[:maxLen-3] + "..."
}
