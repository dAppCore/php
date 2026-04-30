package i18n

import (
	"testing"
	"testing/fstest"
	"time"
)

const testEnglishLocaleFile = "locales/en.json"

func TestI18N_RegisterLocales_Good(t *testing.T) {
	RegisterLocales(fstest.MapFS{testEnglishLocaleFile: {Data: []byte(`{"common":{"label":{"done":"Done"}}}`)}}, "locales")
	got := Label("done")
	if got != "Done" {
		t.Fatalf("Label(done) = %q", got)
	}
}

func TestI18N_RegisterLocales_Bad(t *testing.T) {
	RegisterLocales(fstest.MapFS{}, "missing")
	got := T("missing.key")
	if got != "missing.key" {
		t.Fatalf("T fallback = %q", got)
	}
}

func TestI18N_RegisterLocales_Ugly(t *testing.T) {
	RegisterLocales(fstest.MapFS{"locales/bad.json": {Data: []byte(`{`)}}, "locales")
	got := T("bad.json")
	if got != "bad.json" {
		t.Fatalf("bad locale changed fallback to %q", got)
	}
}

func TestI18N_T_Good(t *testing.T) {
	RegisterLocales(fstest.MapFS{testEnglishLocaleFile: {Data: []byte(`{"hello":"Hello {{.Name}}"}`)}}, "locales")
	got := T("hello", map[string]any{"Name": "Ada"})
	if got != "Hello Ada" {
		t.Fatalf("T rendered %q", got)
	}
}

func TestI18N_T_Bad(t *testing.T) {
	got := T("i18n.unknown")
	if got != "i18n.unknown" {
		t.Fatalf("T fallback = %q", got)
	}
}

func TestI18N_T_Ugly(t *testing.T) {
	RegisterLocales(fstest.MapFS{testEnglishLocaleFile: {Data: []byte(`{"pct":"%s:%s"}`)}}, "locales")
	got := T("pct", "a", "b")
	if got != "a:b" {
		t.Fatalf("T printf render = %q", got)
	}
}

func TestI18N_Label_Good(t *testing.T) {
	RegisterLocales(fstest.MapFS{testEnglishLocaleFile: {Data: []byte(`{"common":{"label":{"status":"Status"}}}`)}}, "locales")
	got := Label("status")
	if got != "Status" {
		t.Fatalf("Label(status) = %q", got)
	}
}

func TestI18N_Label_Bad(t *testing.T) {
	got := Label("definitely_missing")
	if got != "common.label.definitely_missing" {
		t.Fatalf("Label fallback = %q", got)
	}
}

func TestI18N_Label_Ugly(t *testing.T) {
	RegisterLocales(fstest.MapFS{testEnglishLocaleFile: {Data: []byte(`{"common":{"label":{"two_words":"Two Words"}}}`)}}, "locales")
	got := Label("two_words")
	if got != "Two Words" {
		t.Fatalf("Label underscore key = %q", got)
	}
}

func TestI18N_ProgressSubject_Good(t *testing.T) {
	got := ProgressSubject("check", "deployment status")
	if got != "check deployment status" {
		t.Fatalf("ProgressSubject = %q", got)
	}
}

func TestI18N_ProgressSubject_Bad(t *testing.T) {
	got := ProgressSubject("", "")
	if got != "" {
		t.Fatalf("empty ProgressSubject = %q", got)
	}
}

func TestI18N_ProgressSubject_Ugly(t *testing.T) {
	got := ProgressSubject("  run", " job  ")
	if got != "run  job" {
		t.Fatalf("trimmed ProgressSubject = %q", got)
	}
}

func TestI18N_TimeAgo_Good(t *testing.T) {
	got := TimeAgo(time.Now().Add(-2 * time.Second))
	if got == "" {
		t.Fatalf("TimeAgo returned empty")
	}
}

func TestI18N_TimeAgo_Bad(t *testing.T) {
	got := TimeAgo(time.Time{})
	if got != "" {
		t.Fatalf("zero TimeAgo = %q", got)
	}
}

func TestI18N_TimeAgo_Ugly(t *testing.T) {
	got := TimeAgo(time.Now().Add(2 * time.Second))
	if got == "" || got[len(got)-8:] != "from now" {
		t.Fatalf("future TimeAgo = %q", got)
	}
}

func TestI18N_Title_Good(t *testing.T) {
	got := Title("composer_audit")
	if got != "Composer Audit" {
		t.Fatalf("Title = %q", got)
	}
}

func TestI18N_Title_Bad(t *testing.T) {
	got := Title("")
	if got != "" {
		t.Fatalf("empty Title = %q", got)
	}
}

func TestI18N_Title_Ugly(t *testing.T) {
	got := Title("MIXED case")
	if got != "Mixed Case" {
		t.Fatalf("mixed Title = %q", got)
	}
}
