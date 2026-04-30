package php

import (
	"context"
	"os"
	"time"

	"dappco.re/go/cli/pkg/cli"
	"dappco.re/go/i18n"
)

// Deploy command styles (aliases to shared)
var (
	phpDeployStyle        = cli.SuccessStyle
	phpDeployPendingStyle = cli.WarningStyle
	phpDeployFailedStyle  = cli.ErrorStyle
)

func addPHPDeployCommands(parent *cli.Command) {
	// Main deploy command
	addPHPDeployCommand(parent)

	// Deploy status subcommand (using colon notation: deploy:status)
	addPHPDeployStatusCommand(parent)

	// Deploy rollback subcommand
	addPHPDeployRollbackCommand(parent)

	// Deploy list subcommand
	addPHPDeployListCommand(parent)
}

var (
	deployStaging bool
	deployForce   bool
	deployWait    bool
)

func addPHPDeployCommand(parent *cli.Command) {
	deployCmd := &cli.Command{
		Use:   "deploy",
		Short: i18n.T("cmd.php.deploy.short"),
		Long:  i18n.T("cmd.php.deploy.long"),
		RunE: func(cmd *cli.Command, args []string) error {
			cwd, err := os.Getwd()
			if err != nil {
				return cli.Err(cliWrapErrorFormat, i18n.T(i18nFailGetKey, workingDirectorySubject), err)
			}

			env := EnvProduction
			if deployStaging {
				env = EnvStaging
			}

			cli.Print(cliLabelValueBlankFormat, dimStyle.Render(i18n.T(cmdPHPDeployLabelKey)), i18n.T("cmd.php.deploy.deploying", map[string]interface{}{"Environment": env}))

			ctx := context.Background()

			opts := DeployOptions{
				Dir:         cwd,
				Environment: env,
				Force:       deployForce,
				Wait:        deployWait,
			}

			status, err := Deploy(ctx, opts)
			if err != nil {
				return cli.Err(cliWrapErrorFormat, i18n.T("cmd.php.error.deploy_failed"), err)
			}

			printDeploymentStatus(status)

			if deployWait {
				if IsDeploymentSuccessful(status.Status) {
					cli.Print(cliSectionLabelValueFormat, successStyle.Render(i18n.Label("done")), i18n.T("common.success.completed", map[string]any{"Action": "Deployment completed"}))
				} else {
					cli.Print(cliSectionLabelValueFormat, errorStyle.Render(i18n.Label("warning")), i18n.T("cmd.php.deploy.warning_status", map[string]interface{}{"Status": status.Status}))
				}
			} else {
				cli.Print(cliSectionLabelValueFormat, successStyle.Render(i18n.Label("done")), i18n.T("cmd.php.deploy.triggered"))
			}

			return nil
		},
	}

	deployCmd.Flags().BoolVar(&deployStaging, "staging", false, i18n.T("cmd.php.deploy.flag.staging"))
	deployCmd.Flags().BoolVar(&deployForce, "force", false, i18n.T("cmd.php.deploy.flag.force"))
	deployCmd.Flags().BoolVar(&deployWait, "wait", false, i18n.T("cmd.php.deploy.flag.wait"))

	parent.AddCommand(deployCmd)
}

var (
	deployStatusStaging      bool
	deployStatusDeploymentID string
)

func addPHPDeployStatusCommand(parent *cli.Command) {
	statusCmd := &cli.Command{
		Use:   "deploy:status",
		Short: i18n.T("cmd.php.deploy_status.short"),
		Long:  i18n.T("cmd.php.deploy_status.long"),
		RunE: func(cmd *cli.Command, args []string) error {
			cwd, err := os.Getwd()
			if err != nil {
				return cli.Err(cliWrapErrorFormat, i18n.T(i18nFailGetKey, workingDirectorySubject), err)
			}

			env := EnvProduction
			if deployStatusStaging {
				env = EnvStaging
			}

			cli.Print(cliLabelValueBlankFormat, dimStyle.Render(i18n.T(cmdPHPDeployLabelKey)), i18n.ProgressSubject("check", "deployment status"))

			ctx := context.Background()

			opts := StatusOptions{
				Dir:          cwd,
				Environment:  env,
				DeploymentID: deployStatusDeploymentID,
			}

			status, err := DeployStatus(ctx, opts)
			if err != nil {
				return cli.Err(cliWrapErrorFormat, i18n.T(i18nFailGetKey, "status"), err)
			}

			printDeploymentStatus(status)

			return nil
		},
	}

	statusCmd.Flags().BoolVar(&deployStatusStaging, "staging", false, i18n.T("cmd.php.deploy_status.flag.staging"))
	statusCmd.Flags().StringVar(&deployStatusDeploymentID, "id", "", i18n.T("cmd.php.deploy_status.flag.id"))

	parent.AddCommand(statusCmd)
}

var (
	rollbackStaging      bool
	rollbackDeploymentID string
	rollbackWait         bool
)

func addPHPDeployRollbackCommand(parent *cli.Command) {
	rollbackCmd := &cli.Command{
		Use:   "deploy:rollback",
		Short: i18n.T("cmd.php.deploy_rollback.short"),
		Long:  i18n.T("cmd.php.deploy_rollback.long"),
		RunE: func(cmd *cli.Command, args []string) error {
			cwd, err := os.Getwd()
			if err != nil {
				return cli.Err(cliWrapErrorFormat, i18n.T(i18nFailGetKey, workingDirectorySubject), err)
			}

			env := EnvProduction
			if rollbackStaging {
				env = EnvStaging
			}

			cli.Print(cliLabelValueBlankFormat, dimStyle.Render(i18n.T(cmdPHPDeployLabelKey)), i18n.T("cmd.php.deploy_rollback.rolling_back", map[string]interface{}{"Environment": env}))

			ctx := context.Background()

			opts := RollbackOptions{
				Dir:          cwd,
				Environment:  env,
				DeploymentID: rollbackDeploymentID,
				Wait:         rollbackWait,
			}

			status, err := Rollback(ctx, opts)
			if err != nil {
				return cli.Err(cliWrapErrorFormat, i18n.T("cmd.php.error.rollback_failed"), err)
			}

			printDeploymentStatus(status)

			if rollbackWait {
				if IsDeploymentSuccessful(status.Status) {
					cli.Print(cliSectionLabelValueFormat, successStyle.Render(i18n.Label("done")), i18n.T("common.success.completed", map[string]any{"Action": "Rollback completed"}))
				} else {
					cli.Print(cliSectionLabelValueFormat, errorStyle.Render(i18n.Label("warning")), i18n.T("cmd.php.deploy_rollback.warning_status", map[string]interface{}{"Status": status.Status}))
				}
			} else {
				cli.Print(cliSectionLabelValueFormat, successStyle.Render(i18n.Label("done")), i18n.T("cmd.php.deploy_rollback.triggered"))
			}

			return nil
		},
	}

	rollbackCmd.Flags().BoolVar(&rollbackStaging, "staging", false, i18n.T("cmd.php.deploy_rollback.flag.staging"))
	rollbackCmd.Flags().StringVar(&rollbackDeploymentID, "id", "", i18n.T("cmd.php.deploy_rollback.flag.id"))
	rollbackCmd.Flags().BoolVar(&rollbackWait, "wait", false, i18n.T("cmd.php.deploy_rollback.flag.wait"))

	parent.AddCommand(rollbackCmd)
}

var (
	deployListStaging bool
	deployListLimit   int
)

func addPHPDeployListCommand(parent *cli.Command) {
	listCmd := &cli.Command{
		Use:   "deploy:list",
		Short: i18n.T("cmd.php.deploy_list.short"),
		Long:  i18n.T("cmd.php.deploy_list.long"),
		RunE: func(cmd *cli.Command, args []string) error {
			cwd, err := os.Getwd()
			if err != nil {
				return cli.Err(cliWrapErrorFormat, i18n.T(i18nFailGetKey, workingDirectorySubject), err)
			}

			env := EnvProduction
			if deployListStaging {
				env = EnvStaging
			}

			limit := deployListLimit
			if limit == 0 {
				limit = 10
			}

			cli.Print(cliLabelValueBlankFormat, dimStyle.Render(i18n.T(cmdPHPDeployLabelKey)), i18n.T("cmd.php.deploy_list.recent", map[string]interface{}{"Environment": env}))

			ctx := context.Background()

			deployments, err := ListDeployments(ctx, cwd, env, limit)
			if err != nil {
				return cli.Err(cliWrapErrorFormat, i18n.T("i18n.fail.list", "deployments"), err)
			}

			if len(deployments) == 0 {
				cli.Print(cliLabelValueFormat, dimStyle.Render(i18n.T("cmd.php.label.info")), i18n.T("cmd.php.deploy_list.none_found"))
				return nil
			}

			for i, d := range deployments {
				printDeploymentSummary(i+1, &d)
			}

			return nil
		},
	}

	listCmd.Flags().BoolVar(&deployListStaging, "staging", false, i18n.T("cmd.php.deploy_list.flag.staging"))
	listCmd.Flags().IntVar(&deployListLimit, "limit", 0, i18n.T("cmd.php.deploy_list.flag.limit"))

	parent.AddCommand(listCmd)
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
