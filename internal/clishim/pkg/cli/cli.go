package cli

import (
	"errors"
	"fmt"
	"os"
)

type Command struct {
	Use               string
	Short             string
	Long              string
	Args              func(*Command, []string) error
	RunE              func(*Command, []string) error
	PersistentPreRunE func(*Command, []string) error

	flags    FlagSet
	commands []*Command
}

type Option func(*Command)

var Main = func(options ...Option) {
	root := &Command{}
	for _, option := range options {
		if option != nil {
			option(root)
		}
	}
}

var WithCommands = func(use string, register func(*Command)) Option {
	return func(root *Command) {
		root.Use = use
		if register != nil {
			register(root)
		}
	}
}

func (c *Command) AddCommand(commands ...*Command) {
	c.commands = append(c.commands, commands...)
}

func (c *Command) Commands() []*Command {
	return append([]*Command(nil), c.commands...)
}

func (c *Command) Flags() *FlagSet {
	return &c.flags
}

func (c *Command) PersistentFlags() *FlagSet {
	return &c.flags
}

type FlagSet struct{}

func (f *FlagSet) BoolVar(target *bool, name string, value bool, usage string) {
	*target = value
}

func (f *FlagSet) BoolVarP(target *bool, name, shorthand string, value bool, usage string) {
	*target = value
}

func (f *FlagSet) IntVar(target *int, name string, value int, usage string) {
	*target = value
}

func (f *FlagSet) StringVar(target *string, name string, value string, usage string) {
	*target = value
}

func MinimumNArgs(n int) func(*Command, []string) error {
	return func(cmd *Command, args []string) error {
		if len(args) < n {
			return Err("requires at least %d arg(s), only received %d", n, len(args))
		}
		return nil
	}
}

func ExactArgs(n int) func(*Command, []string) error {
	return func(cmd *Command, args []string) error {
		if len(args) != n {
			return Err("requires exactly %d arg(s), received %d", n, len(args))
		}
		return nil
	}
}

func NoArgs(cmd *Command, args []string) error {
	if len(args) > 0 {
		return Err("accepts no args, received %d", len(args))
	}
	return nil
}

func Err(format string, args ...any) error {
	return fmt.Errorf(format, args...)
}

func Wrap(err error, message string) error {
	if err == nil {
		return nil
	}
	return fmt.Errorf("%s: %w", message, err)
}

func WrapVerb(err error, verb string, target string) error {
	if err == nil {
		return nil
	}
	return fmt.Errorf("failed to %s %s: %w", verb, target, err)
}

func Sprintf(format string, args ...any) string {
	return fmt.Sprintf(format, args...)
}

func Print(format string, args ...any) {
	_, _ = fmt.Fprintf(os.Stdout, format, args...)
}

func Warnf(format string, args ...any) {
	_, _ = fmt.Fprintf(os.Stderr, format+"\n", args...)
}

func Blank() {
	_, _ = fmt.Fprintln(os.Stdout)
}

type ExitError struct {
	Code int
	Err  error
}

func (e *ExitError) Error() string {
	if e.Err == nil {
		return fmt.Sprintf("exit status %d", e.Code)
	}
	return e.Err.Error()
}

func (e *ExitError) Unwrap() error {
	return e.Err
}

func Exit(code int, err error) error {
	if err == nil {
		err = errors.New("exit")
	}
	return &ExitError{Code: code, Err: err}
}

type AnsiStyle struct{}

func NewStyle() *AnsiStyle {
	return &AnsiStyle{}
}

func (s *AnsiStyle) Foreground(colour string) *AnsiStyle {
	return s
}

func (s *AnsiStyle) Render(value string) string {
	return value
}

var (
	SuccessStyle = NewStyle()
	ErrorStyle   = NewStyle()
	DimStyle     = NewStyle()
	LinkStyle    = NewStyle()
	WarningStyle = NewStyle()
	BoldStyle    = NewStyle()
)

const (
	ColourIndigo500 = "indigo"
	ColourYellow500 = "yellow"
	ColourOrange500 = "orange"
	ColourViolet500 = "violet"
	ColourRed500    = "red"
)
