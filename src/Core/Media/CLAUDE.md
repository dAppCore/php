# Core\Media

Image processing, media conversion pipeline, and on-demand thumbnail generation.

## Directory Structure

```
Media/
  Abstracts/          MediaConversion base class, Image abstract
  Conversions/        MediaImageResizerConversion, MediaVideoThumbConversion
  Events/             ConversionProgress event
  Image/              ImageOptimization, ImageOptimizer, ExifStripper, ModernFormatSupport, OptimizationResult
  Jobs/               GenerateThumbnail, ProcessMediaConversion
  Routes/             web.php (thumbnail routes)
  Support/            ConversionProgressReporter, TemporaryFile, TemporaryDirectory, ImageResizer, MediaConversionData
  Thumbnail/          ThumbnailController, LazyThumbnail, helpers.php
  Boot.php            Service provider
  config.php          Media configuration
```

## Key Concepts

- **Media Conversions**: Abstract pipeline (`MediaConversion`) for processing uploaded media. Concrete implementations handle image resizing and video thumbnail extraction.
- **Thumbnail System**: On-demand thumbnail generation via `ThumbnailController` with `LazyThumbnail` for deferred processing. Routes registered in `Routes/web.php`.
- **Image Optimization**: `ImageOptimizer` handles format conversion, EXIF stripping, and compression. `ModernFormatSupport` detects WebP/AVIF browser support.
- **Progress Reporting**: `ConversionProgressReporter` + `ConversionProgress` event for tracking long-running media conversions.
- **Temporary Files**: `TemporaryFile` and `TemporaryDirectory` helpers for safe cleanup of processing artifacts.

## Jobs

- `GenerateThumbnail` -- Queued job for thumbnail generation
- `ProcessMediaConversion` -- Queued job for media conversion pipeline

## Integration

- Boot provider registers config from `config.php`
- Thumbnail routes are web routes (not API)
- Conversion data flows through `MediaConversionData` DTO
- `ImageResizer` in Support handles the actual resize operations
