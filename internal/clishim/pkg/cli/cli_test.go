package cli

import (
	"errors"
	"io"
	"os"
	"strings"
	"testing"
)

func captureStdout(t *testing.T, fn func()) string {
	t.Helper()
	old := os.Stdout
	read, write, err := os.Pipe()
	if err != nil {
		t.Fatal(err)
	}
	os.Stdout = write
	fn()
	write.Close()
	os.Stdout = old
	data, err := io.ReadAll(read)
	if err != nil {
		t.Fatal(err)
	}
	return string(data)
}

func captureStderr(t *testing.T, fn func()) string {
	t.Helper()
	old := os.Stderr
	read, write, err := os.Pipe()
	if err != nil {
		t.Fatal(err)
	}
	os.Stderr = write
	fn()
	write.Close()
	os.Stderr = old
	data, err := io.ReadAll(read)
	if err != nil {
		t.Fatal(err)
	}
	return string(data)
}

func TestCLI_Command_AddCommand_Good(t *testing.T) {
	root := &Command{}
	child := &Command{Use: "child"}
	root.AddCommand(child)
	if len(root.commands) != 1 || root.commands[0] != child {
		t.Fatalf("commands = %#v", root.commands)
	}
}

func TestCLI_Command_AddCommand_Bad(t *testing.T) {
	root := &Command{}
	root.AddCommand()
	if len(root.commands) != 0 {
		t.Fatalf("empty add changed commands: %#v", root.commands)
	}
}

func TestCLI_Command_AddCommand_Ugly(t *testing.T) {
	root := &Command{}
	root.AddCommand(nil, &Command{Use: "x"})
	if len(root.commands) != 2 || root.commands[0] != nil {
		t.Fatalf("nil command was not preserved")
	}
}

func TestCLI_Command_Commands_Good(t *testing.T) {
	root := &Command{}
	root.AddCommand(&Command{Use: "child"})
	got := root.Commands()
	if len(got) != 1 || got[0].Use != "child" {
		t.Fatalf("Commands() = %#v", got)
	}
}

func TestCLI_Command_Commands_Bad(t *testing.T) {
	root := &Command{}
	got := root.Commands()
	if len(got) != 0 {
		t.Fatalf("empty Commands() = %#v", got)
	}
}

func TestCLI_Command_Commands_Ugly(t *testing.T) {
	root := &Command{}
	root.AddCommand(&Command{Use: "child"})
	got := root.Commands()
	got[0] = nil
	if root.commands[0] == nil {
		t.Fatalf("Commands leaked backing slice")
	}
}

func TestCLI_Command_Flags_Good(t *testing.T) {
	cmd := &Command{}
	flags := cmd.Flags()
	if flags == nil {
		t.Fatalf("Flags() returned nil")
	}
}

func TestCLI_Command_Flags_Bad(t *testing.T) {
	cmd := &Command{}
	first := cmd.Flags()
	second := cmd.Flags()
	if first != second {
		t.Fatalf("Flags() returned different pointers")
	}
}

func TestCLI_Command_Flags_Ugly(t *testing.T) {
	cmd := &Command{}
	var value bool
	cmd.Flags().BoolVar(&value, "flag", true, "")
	if !value {
		t.Fatalf("BoolVar through Flags did not set value")
	}
}

func TestCLI_Command_PersistentFlags_Good(t *testing.T) {
	cmd := &Command{}
	flags := cmd.PersistentFlags()
	if flags == nil {
		t.Fatalf("PersistentFlags() returned nil")
	}
}

func TestCLI_Command_PersistentFlags_Bad(t *testing.T) {
	cmd := &Command{}
	if cmd.PersistentFlags() != cmd.Flags() {
		t.Fatalf("persistent and regular flags should share storage")
	}
}

func TestCLI_Command_PersistentFlags_Ugly(t *testing.T) {
	cmd := &Command{}
	var value string
	cmd.PersistentFlags().StringVar(&value, "name", "value", "")
	if value != "value" {
		t.Fatalf("StringVar through PersistentFlags = %q", value)
	}
}

func TestCLI_FlagSet_BoolVar_Good(t *testing.T) {
	var value bool
	(&FlagSet{}).BoolVar(&value, "flag", true, "usage")
	if !value {
		t.Fatalf("BoolVar did not assign true")
	}
}

func TestCLI_FlagSet_BoolVar_Bad(t *testing.T) {
	value := true
	(&FlagSet{}).BoolVar(&value, "flag", false, "usage")
	if value {
		t.Fatalf("BoolVar did not assign false")
	}
}

func TestCLI_FlagSet_BoolVar_Ugly(t *testing.T) {
	var value bool
	(&FlagSet{}).BoolVar(&value, "", true, "")
	if !value {
		t.Fatalf("BoolVar with empty name failed")
	}
}

func TestCLI_FlagSet_BoolVarP_Good(t *testing.T) {
	var value bool
	(&FlagSet{}).BoolVarP(&value, "detach", "d", true, "")
	if !value {
		t.Fatalf("BoolVarP did not assign true")
	}
}

func TestCLI_FlagSet_BoolVarP_Bad(t *testing.T) {
	value := true
	(&FlagSet{}).BoolVarP(&value, "detach", "d", false, "")
	if value {
		t.Fatalf("BoolVarP did not assign false")
	}
}

func TestCLI_FlagSet_BoolVarP_Ugly(t *testing.T) {
	var value bool
	(&FlagSet{}).BoolVarP(&value, "", "", true, "")
	if !value {
		t.Fatalf("BoolVarP with empty names failed")
	}
}

func TestCLI_FlagSet_IntVar_Good(t *testing.T) {
	var value int
	(&FlagSet{}).IntVar(&value, "port", 8080, "")
	if value != 8080 {
		t.Fatalf("IntVar = %d", value)
	}
}

func TestCLI_FlagSet_IntVar_Bad(t *testing.T) {
	value := 1
	(&FlagSet{}).IntVar(&value, "port", 0, "")
	if value != 0 {
		t.Fatalf("IntVar zero = %d", value)
	}
}

func TestCLI_FlagSet_IntVar_Ugly(t *testing.T) {
	var value int
	(&FlagSet{}).IntVar(&value, "port", -1, "")
	if value != -1 {
		t.Fatalf("IntVar negative = %d", value)
	}
}

func TestCLI_FlagSet_StringVar_Good(t *testing.T) {
	var value string
	(&FlagSet{}).StringVar(&value, "name", "app", "")
	if value != "app" {
		t.Fatalf("StringVar = %q", value)
	}
}

func TestCLI_FlagSet_StringVar_Bad(t *testing.T) {
	value := "old"
	(&FlagSet{}).StringVar(&value, "name", "", "")
	if value != "" {
		t.Fatalf("StringVar empty = %q", value)
	}
}

func TestCLI_FlagSet_StringVar_Ugly(t *testing.T) {
	var value string
	(&FlagSet{}).StringVar(&value, "", "spaced value", "")
	if value != "spaced value" {
		t.Fatalf("StringVar spaced = %q", value)
	}
}

func TestCLI_MinimumNArgs_Good(t *testing.T) {
	check := MinimumNArgs(2)
	err := check(&Command{}, []string{"a", "b"})
	if err != nil {
		t.Fatalf("MinimumNArgs good = %v", err)
	}
}

func TestCLI_MinimumNArgs_Bad(t *testing.T) {
	check := MinimumNArgs(2)
	err := check(&Command{}, []string{"a"})
	if err == nil {
		t.Fatalf("MinimumNArgs accepted too few args")
	}
}

func TestCLI_MinimumNArgs_Ugly(t *testing.T) {
	check := MinimumNArgs(0)
	err := check(&Command{}, nil)
	if err != nil {
		t.Fatalf("MinimumNArgs zero = %v", err)
	}
}

func TestCLI_ExactArgs_Good(t *testing.T) {
	check := ExactArgs(1)
	err := check(&Command{}, []string{"only"})
	if err != nil {
		t.Fatalf("ExactArgs good = %v", err)
	}
}

func TestCLI_ExactArgs_Bad(t *testing.T) {
	check := ExactArgs(1)
	err := check(&Command{}, nil)
	if err == nil {
		t.Fatalf("ExactArgs accepted too few args")
	}
}

func TestCLI_ExactArgs_Ugly(t *testing.T) {
	check := ExactArgs(0)
	err := check(&Command{}, []string{})
	if err != nil {
		t.Fatalf("ExactArgs zero = %v", err)
	}
}

func TestCLI_NoArgs_Good(t *testing.T) {
	err := NoArgs(&Command{}, nil)
	if err != nil {
		t.Fatalf("NoArgs nil = %v", err)
	}
}

func TestCLI_NoArgs_Bad(t *testing.T) {
	err := NoArgs(&Command{}, []string{"extra"})
	if err == nil {
		t.Fatalf("NoArgs accepted extra arg")
	}
}

func TestCLI_NoArgs_Ugly(t *testing.T) {
	err := NoArgs(nil, []string{})
	if err != nil {
		t.Fatalf("NoArgs nil command = %v", err)
	}
}

func TestCLI_Err_Good(t *testing.T) {
	err := Err("hello %s", "world")
	if err == nil || err.Error() != "hello world" {
		t.Fatalf("Err = %v", err)
	}
}

func TestCLI_Err_Bad(t *testing.T) {
	err := Err("bad")
	if err == nil {
		t.Fatalf("Err returned nil")
	}
}

func TestCLI_Err_Ugly(t *testing.T) {
	err := Err("%w", io.EOF)
	if !errors.Is(err, io.EOF) {
		t.Fatalf("Err wrapping = %v", err)
	}
}

func TestCLI_Wrap_Good(t *testing.T) {
	err := Wrap(io.EOF, "read")
	if !errors.Is(err, io.EOF) || !strings.Contains(err.Error(), "read") {
		t.Fatalf("Wrap = %v", err)
	}
}

func TestCLI_Wrap_Bad(t *testing.T) {
	err := Wrap(nil, "read")
	if err != nil {
		t.Fatalf("Wrap nil = %v", err)
	}
}

func TestCLI_Wrap_Ugly(t *testing.T) {
	err := Wrap(io.EOF, "")
	if !errors.Is(err, io.EOF) {
		t.Fatalf("Wrap empty message = %v", err)
	}
}

func TestCLI_WrapVerb_Good(t *testing.T) {
	err := WrapVerb(io.EOF, "read", "file")
	if !errors.Is(err, io.EOF) || !strings.Contains(err.Error(), "read file") {
		t.Fatalf("WrapVerb = %v", err)
	}
}

func TestCLI_WrapVerb_Bad(t *testing.T) {
	err := WrapVerb(nil, "read", "file")
	if err != nil {
		t.Fatalf("WrapVerb nil = %v", err)
	}
}

func TestCLI_WrapVerb_Ugly(t *testing.T) {
	err := WrapVerb(io.EOF, "", "")
	if !errors.Is(err, io.EOF) {
		t.Fatalf("WrapVerb empty = %v", err)
	}
}

func TestCLI_Sprintf_Good(t *testing.T) {
	got := Sprintf("%s:%d", "port", 80)
	if got != "port:80" {
		t.Fatalf("Sprintf = %q", got)
	}
}

func TestCLI_Sprintf_Bad(t *testing.T) {
	got := Sprintf("plain")
	if got != "plain" {
		t.Fatalf("Sprintf plain = %q", got)
	}
}

func TestCLI_Sprintf_Ugly(t *testing.T) {
	got := Sprintf("%q", "a b")
	if got != "\"a b\"" {
		t.Fatalf("Sprintf quoted = %q", got)
	}
}

func TestCLI_Print_Good(t *testing.T) {
	got := captureStdout(t, func() { Print("hello %s", "world") })
	if got != "hello world" {
		t.Fatalf("Print = %q", got)
	}
}

func TestCLI_Print_Bad(t *testing.T) {
	got := captureStdout(t, func() { Print("") })
	if got != "" {
		t.Fatalf("Print empty = %q", got)
	}
}

func TestCLI_Print_Ugly(t *testing.T) {
	got := captureStdout(t, func() { Print("%s\n%s", "a", "b") })
	if got != "a\nb" {
		t.Fatalf("Print multiline = %q", got)
	}
}

func TestCLI_Warnf_Good(t *testing.T) {
	got := captureStderr(t, func() { Warnf("warn %s", "now") })
	if got != "warn now\n" {
		t.Fatalf("Warnf = %q", got)
	}
}

func TestCLI_Warnf_Bad(t *testing.T) {
	got := captureStderr(t, func() { Warnf("") })
	if got != "\n" {
		t.Fatalf("Warnf empty = %q", got)
	}
}

func TestCLI_Warnf_Ugly(t *testing.T) {
	got := captureStderr(t, func() { Warnf("%s", "x\ny") })
	if got != "x\ny\n" {
		t.Fatalf("Warnf multiline = %q", got)
	}
}

func TestCLI_Blank_Good(t *testing.T) {
	got := captureStdout(t, Blank)
	if got != "\n" {
		t.Fatalf("Blank = %q", got)
	}
}

func TestCLI_Blank_Bad(t *testing.T) {
	got := captureStdout(t, func() { Blank(); Blank() })
	if got != "\n\n" {
		t.Fatalf("double Blank = %q", got)
	}
}

func TestCLI_Blank_Ugly(t *testing.T) {
	got := captureStdout(t, func() {})
	if got != "" {
		t.Fatalf("empty capture = %q", got)
	}
}

func TestCLI_Exit_Good(t *testing.T) {
	err := Exit(2, io.EOF)
	if !errors.Is(err, io.EOF) {
		t.Fatalf("Exit unwrap = %v", err)
	}
}

func TestCLI_Exit_Bad(t *testing.T) {
	err := Exit(1, nil)
	if err == nil {
		t.Fatalf("Exit nil error returned nil")
	}
}

func TestCLI_Exit_Ugly(t *testing.T) {
	err := Exit(0, io.EOF)
	if got := err.(*ExitError).Code; got != 0 {
		t.Fatalf("Exit code = %d", got)
	}
}

func TestCLI_ExitError_Error_Good(t *testing.T) {
	err := &ExitError{Code: 3, Err: io.EOF}
	got := err.Error()
	if got != io.EOF.Error() {
		t.Fatalf("ExitError Error = %q", got)
	}
}

func TestCLI_ExitError_Error_Bad(t *testing.T) {
	err := &ExitError{Code: 3}
	got := err.Error()
	if !strings.Contains(got, "3") {
		t.Fatalf("ExitError nil = %q", got)
	}
}

func TestCLI_ExitError_Error_Ugly(t *testing.T) {
	err := &ExitError{Code: -1}
	got := err.Error()
	if !strings.Contains(got, "-1") {
		t.Fatalf("ExitError negative = %q", got)
	}
}

func TestCLI_ExitError_Unwrap_Good(t *testing.T) {
	err := &ExitError{Err: io.EOF}
	got := err.Unwrap()
	if got != io.EOF {
		t.Fatalf("Unwrap = %v", got)
	}
}

func TestCLI_ExitError_Unwrap_Bad(t *testing.T) {
	err := &ExitError{}
	got := err.Unwrap()
	if got != nil {
		t.Fatalf("Unwrap nil = %v", got)
	}
}

func TestCLI_ExitError_Unwrap_Ugly(t *testing.T) {
	inner := errors.New("inner")
	err := &ExitError{Err: inner}
	if !errors.Is(err, inner) {
		t.Fatalf("errors.Is did not unwrap")
	}
}

func TestCLI_NewStyle_Good(t *testing.T) {
	style := NewStyle()
	if style == nil {
		t.Fatalf("NewStyle returned nil")
	}
}

func TestCLI_NewStyle_Bad(t *testing.T) {
	first := NewStyle()
	second := NewStyle()
	if first == second {
		t.Fatalf("NewStyle reused pointer")
	}
}

func TestCLI_NewStyle_Ugly(t *testing.T) {
	style := NewStyle().Foreground(ColourRed500)
	if style == nil {
		t.Fatalf("NewStyle chained nil")
	}
}

func TestCLI_AnsiStyle_Foreground_Good(t *testing.T) {
	style := NewStyle()
	got := style.Foreground(ColourIndigo500)
	if got != style {
		t.Fatalf("Foreground returned different style")
	}
}

func TestCLI_AnsiStyle_Foreground_Bad(t *testing.T) {
	style := NewStyle()
	got := style.Foreground("")
	if got != style {
		t.Fatalf("Foreground empty returned different style")
	}
}

func TestCLI_AnsiStyle_Foreground_Ugly(t *testing.T) {
	style := NewStyle()
	got := style.Foreground("not-a-colour").Foreground(ColourYellow500)
	if got != style {
		t.Fatalf("Foreground chain returned different style")
	}
}

func TestCLI_AnsiStyle_Render_Good(t *testing.T) {
	got := NewStyle().Render("hello")
	if got != "hello" {
		t.Fatalf("Render = %q", got)
	}
}

func TestCLI_AnsiStyle_Render_Bad(t *testing.T) {
	got := NewStyle().Render("")
	if got != "" {
		t.Fatalf("Render empty = %q", got)
	}
}

func TestCLI_AnsiStyle_Render_Ugly(t *testing.T) {
	got := NewStyle().Render("multi\nline")
	if got != "multi\nline" {
		t.Fatalf("Render multiline = %q", got)
	}
}
