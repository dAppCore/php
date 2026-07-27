# Media/Support/ — Media Processing Utilities

## Files

| File | Purpose |
|------|---------|
| `ImageResizer` | Image resizing with aspect ratio preservation and memory safety checks. Prevents upscaling and OOM crashes. |
| `ConversionProgressReporter` | Reports media conversion progress to listeners via `ConversionProgress` event. |
| `MediaConversionData` | DTO carrying conversion parameters — source path, output path, dimensions, format, quality. |
| `TemporaryDirectory` | Manages temporary directories for media processing with automatic cleanup. |
| `TemporaryFile` | Manages temporary files for media processing with automatic cleanup. |
