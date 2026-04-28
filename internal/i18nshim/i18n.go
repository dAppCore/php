package i18n

import (
	"encoding/json"
	"fmt"
	"io/fs"
	"path/filepath"
	"strings"
	"sync"
	"time"
)

var (
	mu           sync.RWMutex
	translations = map[string]string{}
)

func RegisterLocales(fsys fs.FS, root string) {
	entries, err := fs.ReadDir(fsys, root)
	if err != nil {
		return
	}

	loaded := map[string]string{}
	for _, entry := range entries {
		if entry.IsDir() || !strings.HasSuffix(entry.Name(), ".json") {
			continue
		}
		data, err := fs.ReadFile(fsys, filepath.Join(root, entry.Name()))
		if err != nil {
			continue
		}
		var raw map[string]any
		if err := json.Unmarshal(data, &raw); err != nil {
			continue
		}
		flatten("", raw, loaded)
	}

	mu.Lock()
	for key, value := range loaded {
		translations[key] = value
	}
	mu.Unlock()
}

func T(key string, args ...any) string {
	mu.RLock()
	value := translations[key]
	mu.RUnlock()
	if value == "" {
		value = key
	}
	return render(value, args...)
}

func Label(key string) string {
	return T("common.label." + key)
}

func ProgressSubject(verb, subject string) string {
	return strings.TrimSpace(verb + " " + subject)
}

func TimeAgo(t time.Time) string {
	if t.IsZero() {
		return ""
	}
	d := time.Since(t).Round(time.Second)
	if d < 0 {
		d = -d
		return d.String() + " from now"
	}
	return d.String() + " ago"
}

func Title(value string) string {
	if value == "" {
		return ""
	}
	parts := strings.Fields(strings.ReplaceAll(value, "_", " "))
	for i, part := range parts {
		if part == "" {
			continue
		}
		parts[i] = strings.ToUpper(part[:1]) + strings.ToLower(part[1:])
	}
	return strings.Join(parts, " ")
}

func flatten(prefix string, value any, out map[string]string) {
	switch typed := value.(type) {
	case map[string]any:
		for key, child := range typed {
			next := key
			if prefix != "" {
				next = prefix + "." + key
			}
			flatten(next, child, out)
		}
	case string:
		out[prefix] = typed
	}
}

func render(template string, args ...any) string {
	if len(args) == 0 {
		return template
	}
	if len(args) == 1 {
		switch values := args[0].(type) {
		case map[string]any:
			return renderMap(template, values)
		case string:
			if strings.Contains(template, "%") {
				return fmt.Sprintf(template, values)
			}
		}
	}
	if strings.Contains(template, "%") {
		return fmt.Sprintf(template, args...)
	}
	return template
}

func renderMap(template string, values map[string]any) string {
	result := template
	for key, value := range values {
		result = strings.ReplaceAll(result, "{{."+key+"}}", fmt.Sprint(value))
	}
	return result
}
